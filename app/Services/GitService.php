<?php

namespace Modules\SystemBase\app\Services;

use Composer\Semver\Semver;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use Exception;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\BaseService;

class GitService extends BaseService
{
    const requireStrategyNone = 'NONE';
    const requireStrategyDefault = 'DEFAULT';
    const requireStrategyPull = 'PULL';
    const requireStrategyMerge = 'MERGE';
    const requireStrategyNoGit = 'NO_GIT'; // files only

    /**
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
     * @param $url
     * @return bool
     * @todo: Not working?
     */
    public function testRemote($url): bool
    {
        $git = new Git;
        return $git->isRemoteUrlReadable($url);
    }

    /**
     * @param  string  $path
     * @param  bool  $fetch
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
     * @param  string  $srcUrl
     * @param  string  $destPath
     *
     * @return bool
     */
    public function createRepository(string $srcUrl, string $destPath): bool
    {
        $git = new Git;

        try {
            $this->gitRepository = $git->cloneRepository($srcUrl, $destPath);
        } catch (Exception $ex) {
            $this->error($ex->getMessage(), [__METHOD__]);
            return false;
        }

        return !!$this->gitRepository;
    }

    /**
     * Open or Create a repository.
     *
     * @param  string  $repositoryFilePath
     * @param  string  $gitSourceUrl
     * @param  bool  $mustClean  if true and hasChanges() false will return
     * @return bool
     * @throws GitException
     */
    public function ensureRepository(string $repositoryFilePath, string $gitSourceUrl, bool $mustClean): bool
    {
        $this->incrementIndent();

        if (!is_dir($repositoryFilePath)) {

            app('system_base_file')->createDir($repositoryFilePath);

            if (!$this->createRepository($gitSourceUrl, $repositoryFilePath)) {
                $this->error(sprintf("Unable to create git repository '%s' to '%s'", $gitSourceUrl,
                    $repositoryFilePath));

                // remove dir which was created right now
                rmdir($repositoryFilePath);

                $this->decrementIndent();
                return false;
            }

            $this->repositoryJustUpdated = true;

        } else {

            // $this->debug(sprintf("Checking existing module repository: %s", $repositoryFilePath));

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
     * Processing the following steps:
     * 1) git fetch
     * 2) git checkout
     * 3) git pull/merge
     *
     * @param  string  $configRequiredConstraint
     * @param  bool  $allowRemotePull
     * @return bool
     * @throws GitException
     */
    public function repositoryUpdate(string $configRequiredConstraint, bool $allowRemotePull = true): bool
    {
        $strategy = config('deploy-env.require_strategy');
        $this->debug(sprintf("Update strategy: '%s'", $strategy));

        // remember prev commit
        $prevCommitId = $this->getCommitId();

        // fetch repo infos
        $this->repositoryFetch();

        if ($configRequiredConstraint) {
            // $this->debug("config required constraint: ".$configRequiredConstraint);
            if ($checkoutName = $this->findBestTagOrBranch($configRequiredConstraint)) {
                if (in_array($strategy,
                    [self::requireStrategyDefault, self::requireStrategyMerge, self::requireStrategyPull])) {
                    $this->debug(sprintf("Trying to checkout '%s' to '%s' ...", $checkoutName,
                        $this->gitRepository->getRepositoryPath()));
                    if (!$this->repositoryCheckout($checkoutName)) {
                        $this->error(sprintf("Failed to checkout: %s", $checkoutName));
                        $this->decrementIndent();
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
                if (!$this->repositoryPull($prevCommitId)) {
                    $this->error(sprintf("Unable to pull branch: %s", $this->getCurrentBranch()));
                    $this->decrementIndent();
                    return false;
                }
            } else {
                $this->debug("Process 'git pull' not allowed. Skipped.");
            }
        }

        return true;
    }


    /**
     * @param  string  $prevCommitId
     * @return bool
     * @throws GitException
     */
    public function repositoryPull(string $prevCommitId): bool
    {
        try {
            $lastCommitId = $this->gitRepository->getLastCommitId();
            $this->debug(sprintf("Try to pull '%s' to previous '%s'", $lastCommitId, $prevCommitId));

            // merge
            // $this->gitRepository->merge($lastCommitId);
            $this->gitRepository->pull(null, ['--rebase', '--prune', '--tags']);
            // $this->gitRepository->pull(null, ['--rebase']);

        } catch (Exception $ex) {
            $this->error($ex->getMessage(), [__METHOD__, $this->gitRepository->getRepositoryPath()]);
            // Do not return false here. It could be a warning like "Unable to pull branch: (HEAD detached at xxx)" for tags
            // return false;
        }

        // compare new commit
        if ($prevCommitId !== $this->getCommitId()) {
            $this->repositoryJustUpdated = true;
            $this->debug(sprintf("Current commit id: %s", $lastCommitId));
        }
        return true;
    }

    /**
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
     * @param  string  $branchName
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
     * @param  string  $path
     * @return bool
     * @throws \CzProject\GitPhp\GitException
     */
    public function initRepository(string $path): bool
    {
        $git = new Git;
        $this->gitRepository = $git->init($path);

        return !!$this->gitRepository;
    }

    /**
     * @return string
     * @throws \CzProject\GitPhp\GitException
     */
    public function getCurrentBranch(): string
    {
        return $this->gitRepository->getCurrentBranchName();
    }

    /**
     * @return bool
     * @throws \CzProject\GitPhp\GitException
     */
    public function hasChanges(): bool
    {
        return $this->gitRepository->hasChanges();
    }

    /**
     * @return bool
     * @throws \CzProject\GitPhp\GitException
     */
    public function isClean(): bool
    {
        return !$this->hasChanges();
    }

    public function getLocalBranches(): ?array
    {
        return $this->gitRepository->getLocalBranches();
    }

    public function getRemoteBranches(bool $useLocalNames = true): ?array
    {
        $result = $this->gitRepository->getRemoteBranches();

        if ($useLocalNames) {
            $result2 = [];
            foreach ($result as $b) {
                $b = preg_replace("#.*?/(.*)?#", '${1}', $b);
                $result2[] = $b;
            }

            $result = $result2;
        }

        return $result;
    }

    public function getTags(): ?array
    {
        return $this->gitRepository->getTags();
    }

    /**
     * @param  string  $name
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
     * @param  array  $versionList
     * @param  string  $constraint
     * @return string|null
     */
    public function findBestVersion(array $versionList, string $constraint): ?string
    {
        return $this->findSatisfiedVersion($versionList, $constraint);
    }

    /**
     * Find the best version declared by $constraint in list $versionList
     *
     * @param  array  $versionList
     * @param  string  $constraint
     * @return string|null
     */
    public function findSatisfiedVersion(array $versionList, string $constraint): ?string
    {
        $versionList = Semver::rsort($versionList);
        if ($tags = Semver::satisfiedBy($versionList, $constraint)) {
            return $tags[0];
        }

        return null;
    }

    /**
     * @param  array  $branchList
     * @param  string  $constraint
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
            if (preg_match('#'.$constraint.'#', $branch)) {
                return $branch;
            }
        }

        return null;
    }

    /**
     * Searching first for tags and if nothing was found for branches to get the best version in $constraint
     *
     * repositoryFetch() should be performed before!
     *
     * @param  string  $constraint
     * @return string
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
     * @return string
     * @throws GitException
     */
    public function getCommitId(): string
    {
        return $this->gitRepository->getLastCommitId()->toString();
    }

}