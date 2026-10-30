<?php

namespace Modules\SystemBase\app\Services;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use Exception;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\BaseService;
use UnexpectedValueException;

/**
 * Git helpers based on czproject/git-php.
 * Works on one repository at a time: open/clone/init it first, then use the other methods.
 */
class GitService extends BaseService
{
    /**
     * Strategies for repositoryUpdate(), config 'deploy-env.require_strategy'.
     * NONE: only fetch. DEFAULT/PULL: checkout and pull. MERGE: checkout only. NO_GIT: files only.
     */
    const string requireStrategyNone = 'NONE';
    const string requireStrategyDefault = 'DEFAULT';
    const string requireStrategyPull = 'PULL';
    const string requireStrategyMerge = 'MERGE';
    const string requireStrategyNoGit = 'NO_GIT'; // files only

    /**
     * The current repository.
     *
     * @var GitRepository|null
     */
    protected ?GitRepository $gitRepository = null;

    /**
     * True if the repository was pulled into a new commit or new created.
     *
     * @var bool
     */
    public bool $repositoryJustUpdated = false;

    /**
     * True if the remote url is readable.
     *
     * @param  string  $url
     *
     * @return bool
     * @todo: Not working?
     */
    public function testRemote($url): bool
    {
        $git = new Git;

        return $git->isRemoteUrlReadable($url);
    }

    /**
     * Opens an existing repository as the current one.
     *
     * @param  string  $path
     * @param  bool    $fetch  also run "git fetch --tags"
     *
     * @return bool
     */
    public function openRepository(string $path, bool $fetch = false): bool
    {
        $git = new Git;
        $this->gitRepository = $git->open($path);

        if ($fetch && $this->gitRepository) {
            $this->repositoryFetch();
        }

        return !!$this->gitRepository;
    }

    /**
     * Clones a repository and uses it as the current one. Errors are logged.
     *
     * @param  string  $srcUrl
     * @param  string  $destPath
     * @param  array   $cloneParams  like ['--depth' => 1]
     *
     * @return bool
     */
    public function repositoryClone(string $srcUrl, string $destPath, array $cloneParams): bool
    {
        $git = new Git;

        try {
            $this->gitRepository = $git->cloneRepository($srcUrl, $destPath, $cloneParams);
        } catch (Exception $ex) {
            $this->error($ex->getMessage(), [__METHOD__]);

            return false;
        }

        return !!$this->gitRepository;
    }

    /**
     * Opens or creates a repository.
     * Clones it if the directory does not exist.
     *
     * @param  string  $repositoryFilePath
     * @param  string  $gitSourceUrl
     * @param  bool    $mustClean  if true and the existing repository has changes, false is returned
     * @param  array   $cloneParams
     *
     * @return bool
     * @throws GitException
     */
    public function ensureClonedRepository(string $repositoryFilePath, string $gitSourceUrl, bool $mustClean, array $cloneParams = []): bool
    {
        $this->incrementIndent();

        if (!is_dir($repositoryFilePath)) {

            app('system_base_file')->createDir($repositoryFilePath);

            if (!$this->repositoryClone($gitSourceUrl, $repositoryFilePath, $cloneParams)) {
                $this->error(sprintf("Unable to create git repository '%s' to '%s'",
                    $gitSourceUrl,
                    $repositoryFilePath));

                // remove dir which was created right now
                @rmdir($repositoryFilePath);

                $this->decrementIndent();

                return false;
            }

            $this->repositoryJustUpdated = true;

        } else {

            if ($this->openRepository($repositoryFilePath)) {

                if ($mustClean) {
                    if ($this->hasChanges()) {
                        $this->error(sprintf("Git repository has changes: %s", $repositoryFilePath));
                        $this->decrementIndent();

                        return false;
                    }
                }

            } else {
                $this->error(sprintf("Failed to open git repository: %s", $repositoryFilePath));
                $this->decrementIndent();

                return false;
            }

        }

        $this->decrementIndent();

        return true;
    }

    /**
     * Processing the following steps (depending on config 'deploy-env.require_strategy'):
     * 1) git fetch
     * 2) git checkout of the best tag or branch for $configRequiredConstraint (DEFAULT, MERGE, PULL)
     * 3) git pull (DEFAULT, PULL and $allowRemotePull)
     *
     * @param  string  $configRequiredConstraint  like '^1.0' or 'dev-master', '' to keep the current commit
     * @param  bool    $allowRemotePull
     *
     * @return bool
     * @throws GitException
     */
    public function repositoryUpdate(string $configRequiredConstraint, bool $allowRemotePull = true): bool
    {
        $strategy = config('deploy-env.require_strategy');
        $this->debug(sprintf("Update strategy: '%s'", $strategy));

        try {
            // remember prev commit
            $prevCommitId = $this->getCommitId();
        } catch (Exception) {
            $prevCommitId = '';
        }

        // fetch repo infos
        $this->repositoryFetch();

        if ($configRequiredConstraint) {
            if ($checkoutName = $this->findBestTagOrBranch($configRequiredConstraint)) {
                if (in_array($strategy,
                    [self::requireStrategyDefault, self::requireStrategyMerge, self::requireStrategyPull])
                ) {
                    $this->debug(sprintf("Trying to checkout '%s' to '%s' ...",
                        $checkoutName,
                        $this->gitRepository->getRepositoryPath()));
                    if (!$this->repositoryCheckout($checkoutName)) {
                        $this->error(sprintf("Failed to checkout: %s", $checkoutName));

                        return false;
                    }
                }
            } else {
                $this->error(sprintf("Nothing matched to checkout with: %s", $configRequiredConstraint));

                return false;
            }
        } // else constraint not defined, use current checked out commit

        if (in_array($strategy, [self::requireStrategyDefault, self::requireStrategyPull])) {
            if ($allowRemotePull) {
                // Pull current branch (or just new checked out branch/version)
                if ($prevCommitId && !$this->repositoryPull($prevCommitId)) {
                    $this->error(sprintf("Unable to pull branch: %s", $this->getCurrentBranch()));

                    return false;
                }
            } else {
                $this->debug("Process 'git pull' not allowed. Skipped.");
            }
        }

        return true;
    }


    /**
     * Runs "git pull --rebase --prune --tags".
     * A failing pull is only logged (it's normal for checked out tags, "HEAD detached"),
     * so this always returns true.
     * Sets $repositoryJustUpdated if the commit changed compared to $prevCommitId.
     *
     * @param  string  $prevCommitId
     *
     * @return bool
     * @throws GitException
     */
    public function repositoryPull(string $prevCommitId): bool
    {
        try {
            $this->debug(sprintf("Try to pull '%s' to previous '%s'", $this->getCommitId(), $prevCommitId));
            $this->gitRepository->pull(null, ['--rebase', '--prune', '--tags']);
        } catch (Exception $ex) {
            $this->error($ex->getMessage(), [__METHOD__, $this->gitRepository->getRepositoryPath()]);
        }

        // compare new commit
        $currentCommitId = $this->getCommitId();
        if ($prevCommitId !== $currentCommitId) {
            $this->repositoryJustUpdated = true;
            $this->debug(sprintf("Current commit id: %s", $currentCommitId));
        }

        return true;
    }

    /**
     * Runs "git fetch --tags". Errors are logged.
     *
     * @return bool
     */
    public function repositoryFetch(): bool
    {
        try {
            $this->gitRepository->fetch(null, ['--tags']);

            return true;
        } catch (Exception $ex) {
            $this->error($ex->getMessage(), [__METHOD__]);

            return false;
        }
    }

    /**
     * Checks out $branchName if it's not the current branch. Errors are logged.
     *
     * @param  string  $branchName
     *
     * @return bool
     */
    public function ensureBranch(string $branchName): bool
    {
        try {
            if ($this->gitRepository->getCurrentBranchName() !== $branchName) {
                $this->gitRepository->checkout($branchName);
            }
        } catch (Exception $ex) {
            $this->error($ex->getMessage(), [__METHOD__]);

            return false;
        }

        return true;
    }

    /**
     * Runs "git init" and uses the new repository as the current one.
     *
     * @param  string  $path
     *
     * @return bool
     * @throws GitException
     */
    public function repositoryInit(string $path): bool
    {
        $git = new Git;
        $this->gitRepository = $git->init($path);

        return !!$this->gitRepository;
    }

    /**
     * @return string
     * @throws GitException
     */
    public function getCurrentBranch(): string
    {
        return $this->gitRepository->getCurrentBranchName();
    }

    /**
     * True if the working tree has uncommitted changes.
     *
     * @return bool
     * @throws GitException
     */
    public function hasChanges(): bool
    {
        return $this->gitRepository->hasChanges();
    }

    /**
     * @return bool
     * @throws GitException
     */
    public function isClean(): bool
    {
        return !$this->hasChanges();
    }

    /**
     * @return array|null
     * @throws GitException
     */
    public function getLocalBranches(): ?array
    {
        return $this->gitRepository->getLocalBranches();
    }

    /**
     * Remote branches like "origin/master", or "master" with $useLocalNames.
     *
     * @param  bool  $useLocalNames  removes the remote name (everything up to the first '/')
     *
     * @return array|null
     * @throws GitException
     */
    public function getRemoteBranches(bool $useLocalNames = true): ?array
    {
        $result = $this->gitRepository->getRemoteBranches();

        if ($useLocalNames && $result) {
            $result = array_map(fn(string $branch) => preg_replace('#^.*?/#', '', $branch), $result);
        }

        return $result;
    }

    /**
     * @return array|null
     * @throws GitException
     */
    public function getTags(): ?array
    {
        return $this->gitRepository->getTags();
    }

    /**
     * Checks out a branch, tag or commit. Errors are logged.
     *
     * @param  string  $name
     *
     * @return bool
     */
    public function repositoryCheckout(string $name): bool
    {
        try {
            $this->gitRepository->checkout($name);

            return true;
        } catch (Exception $ex) {
            $this->error($ex->getMessage(), [__METHOD__]);

            return false;
        }
    }

    /**
     * wrapper for findSatisfiedVersion()
     *
     * @param  array   $versionList
     * @param  string  $constraint
     *
     * @return string|null
     */
    public function findBestVersion(array $versionList, string $constraint): ?string
    {
        return $this->findSatisfiedVersion($versionList, $constraint);
    }

    /**
     * Find the highest version in $versionList matching the composer constraint like '^1.0'.
     * Entries which are no valid versions (like 'latest' or 'test-tag') are ignored.
     * An invalid constraint returns null.
     *
     * @param  array   $versionList
     * @param  string  $constraint
     *
     * @return string|null
     */
    public function findSatisfiedVersion(array $versionList, string $constraint): ?string
    {
        $versionParser = new VersionParser();
        $validVersions = array_values(array_filter($versionList, function ($version) use ($versionParser) {
            try {
                $versionParser->normalize((string) $version);

                return true;
            } catch (UnexpectedValueException) {
                return false;
            }
        }));

        try {
            $tags = Semver::satisfiedBy(Semver::rsort($validVersions), $constraint);
        } catch (UnexpectedValueException) {
            // constraint like 'dev-master' is no version constraint
            return null;
        }

        return $tags[0] ?? null;
    }

    /**
     * First branch matching $constraint as regular expression for the whole name (a leading 'dev-' is removed).
     * Entries like "HEAD -> origin/master" are skipped.
     *
     * @param  array   $branchList
     * @param  string  $constraint
     *
     * @return string|null
     * @todo: have to be dissolved to composer functionality
     */
    public function findSatisfiedBranch(array $branchList, string $constraint): ?string
    {
        // @todo: "dev-" just removed here - adjust to composer standards later
        if (Str::startsWith($constraint, 'dev-')) {
            $constraint = Str::replaceStart('dev-', '', $constraint);
        }

        foreach ($branchList as $branch) {
            // avoiding "->" like "HEAD -> origin/master"
            if (str_contains($branch, '->')) {
                continue;
            }
            // the whole name: "feature-x" must not match "old-feature-x"
            if (preg_match('#^'.$constraint.'$#', $branch)) {
                return $branch;
            }
        }

        return null;
    }

    /**
     * Searches the best tag for $constraint first, then a matching remote branch.
     *
     * repositoryFetch() should be performed before!
     *
     * @param  string  $constraint
     *
     * @return string  '' if nothing matches
     */
    public function findBestTagOrBranch(string $constraint): string
    {
        $tags = $this->getTags() ?? [];
        if ($tag = $this->findSatisfiedVersion($tags, $constraint)) {
            return $tag;
        }

        $branches = $this->getRemoteBranches() ?? [];
        if ($branch = $this->findSatisfiedBranch($branches, $constraint)) {
            return $branch;
        }

        return '';
    }

    /**
     * Hash of the current commit (HEAD).
     *
     * @return string
     * @throws GitException
     */
    public function getCommitId(): string
    {
        return $this->gitRepository->getLastCommitId()->toString();
    }
}