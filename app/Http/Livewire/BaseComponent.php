<?php
declare(strict_types = 1);

namespace Modules\SystemBase\app\Http\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\SystemBase\app\Events\Livewire\BaseComponentActionCalled;
use Throwable;

/**
 * Base for Livewire components.
 *
 * Lifecycle hooks to overwrite (instead of Livewire's mount/hydrate/booted):
 * - initMount(): first request only
 * - initHydrate(): every following request (default: clears old messages)
 * - init(): after initMount() and after initHydrate()
 * - initBooted(): every request
 *
 * Also provides frontend messages (error/success/info/warning) which are cleared
 * after $messagesLifeTime seconds.
 */
class BaseComponent extends Component
{
    /**
     * Restrictions to allow this component (acl resource codes).
     */
    const array aclResources = [];

    /**
     * Frontend messages.
     *
     * @var array
     */
    public array $baseMessages = [];

    /**
     * Unix time of the last message, used to decide when messages are cleared.
     *
     * @var int
     */
    public int $timeLastMessage = 0;

    /**
     * Lifetime in seconds after which messages are cleared automatically.
     *
     * @var int
     */
    public int $messagesLifeTime = 2;

    /**
     * This data depends on the parent livewire (if any).
     * Example: if the parent is a User and this is a Token, the token can access parentData.id which is the user id.
     * Assign it as parameter in blades.
     *
     * @var array
     */
    public array $parentData = [];

    /**
     * @param  string|null  $id
     */
    public function __construct($id = null)
    {
        $this->setId($id);

        $this->clearMessages();
    }

    /**
     * Overwrite this if you need the same code after mount() and hydrate().
     *
     * @return void
     */
    protected function init(): void
    {

    }

    /**
     * Should be overwritten.
     *
     * Runs on every subsequent request, after the component is hydrated, but before an action is performed or render() is called.
     * This doesn't run on the initial request ("mount" does).
     *
     * @return void
     */
    protected function initHydrate(): void
    {
        // Important but may be replaced! clear messages for next interaction!
        $this->checkClearMessages();
    }

    /**
     * Livewire hook: runs on every subsequent request, after the component is hydrated,
     * but before an action is performed or render() is called.
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->initHydrate();
        $this->init();
    }

    /**
     * Should be overwritten.
     *
     * Runs once, immediately after the component is instantiated, but before render() is called.
     * This is only called once on initial page load and never called again, even on component refreshes.
     *
     * @return void
     */
    protected function initMount(): void
    {
    }

    /**
     * Livewire hook: runs once, immediately after the component is instantiated, but before render() is called.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->initMount();
        $this->init();
    }

    /**
     * Runs on every request, after the component is mounted or hydrated, but before any update methods are called.
     * Provides the default message box buttons and takes over messages stored in the session.
     *
     * @return void
     */
    protected function initBooted(): void
    {
        app('system_base')->provideMessageBoxButtons(category: 'default');

        // messages stored in session (see addMessage() $tempSession) are taken over once
        try {
            if ($sessionMessages = session()->get('messages')) {
                // assign
                $this->baseMessages = $sessionMessages;
                // delete
                session()->forget('messages');
            }
        } catch (Throwable $exception) {
            Log::error($exception->getMessage(), [__METHOD__]);
        }
    }

    /**
     * Livewire hook: runs on every request, after the component is mounted or hydrated, but before any update methods are called.
     *
     * @return void
     */
    public function booted(): void
    {
        $this->initBooted();
    }

    /**
     * Adds a message. Use $tempSession for redirects to different livewires.
     *
     * @param  string  $message
     * @param  string  $key          'error', 'success', 'info' or 'warning'
     * @param  bool    $tempSession  if true, all current messages are also stored in the session
     *
     * @return void
     */
    public function addMessage(string $message, string $key = 'info', bool $tempSession = false): void
    {
        $this->checkClearMessages();
        $this->baseMessages[$key][] = $message;
        $this->timeLastMessage = time();

        if ($tempSession) {
            session()->put('messages', $this->baseMessages);
        }
    }

    /**
     * @param  string  $message
     * @param  bool    $tempSession
     *
     * @return void
     */
    public function addErrorMessage(string $message, bool $tempSession = false): void
    {
        $this->addMessage($message, 'error', $tempSession);
    }

    /**
     * @param  iterable  $messages
     * @param  bool      $tempSession
     *
     * @return void
     */
    public function addErrorMessages(iterable $messages, bool $tempSession = false): void
    {
        foreach ($messages as $message) {
            $this->addErrorMessage($message, $tempSession);
        }
    }

    /**
     * @param  string  $message
     * @param  bool    $tempSession
     *
     * @return void
     */
    public function addSuccessMessage(string $message, bool $tempSession = false): void
    {
        $this->addMessage($message, 'success', $tempSession);
    }

    /**
     * @param  iterable  $messages
     * @param  bool      $tempSession
     *
     * @return void
     */
    public function addSuccessMessages(iterable $messages, bool $tempSession = false): void
    {
        foreach ($messages as $message) {
            $this->addSuccessMessage($message, $tempSession);
        }
    }

    /**
     * @param  string  $message
     * @param  bool    $tempSession
     *
     * @return void
     */
    public function addInfoMessage(string $message, bool $tempSession = false): void
    {
        $this->addMessage($message, 'info', $tempSession);
    }

    /**
     * @param  string  $message
     * @param  bool    $tempSession
     *
     * @return void
     */
    public function addWarningMessage(string $message, bool $tempSession = false): void
    {
        $this->addMessage($message, 'warning', $tempSession);
    }

    /**
     * Listener for 'refresh'. Empty on purpose: a call is enough to re-render the component.
     *
     * @return void
     * @internal Refresh in general seems not working without it ...
     */
    #[On('refresh')]
    public function anotherRefresh(): void
    {
    }

    /**
     * Clears the messages if they are older than $messagesLifeTime.
     *
     * @return void
     */
    #[On('check-reset-messages')]
    public function checkClearMessages(): void
    {
        if (time() - $this->timeLastMessage > $this->messagesLifeTime) {
            $this->clearMessages();
        }
    }

    /**
     * Clears all messages. Can be dispatched by 'reset-messages'.
     *
     * @return void
     */
    #[On('reset-messages')]
    public function resetMessages(): void
    {
        $this->clearMessages();
    }

    /**
     * Clears all messages. Call this inside the component instead of resetMessages().
     *
     * @return void
     */
    public function clearMessages(): void
    {
        $this->baseMessages = [
            'error'   => [],
            'success' => [],
            'info'    => [],
        ];
    }

    /**
     * True if $livewireId is the id of this component, otherwise an error is logged.
     *
     * @param  string  $livewireId
     *
     * @return bool
     */
    protected function checkLivewireId($livewireId): bool
    {
        if ($livewireId !== $this->getId()) {
            Log::error(sprintf("Livewire id '%s' does not match.", $livewireId), [__METHOD__]);

            return false;
        }

        return true;
    }

    /**
     * Builds a string for wire:click="..." like "save('abc123','42');".
     * Params are quoted with ' but not escaped.
     *
     * @param  string  $methodName
     * @param  array   $params
     * @param  bool    $fistParamId  add the component id as first param
     *
     * @return string
     */
    protected function getWireCallString(string $methodName, array $params = [], bool $fistParamId = true): string
    {
        if ($fistParamId) {
            array_unshift($params, $this->getId());
        }
        $params = array_map(fn($x) => "'".$x."'", $params);
        $paramsStr = implode(",", $params);

        return $methodName.'('.$paramsStr.');';
    }

    /**
     * Dispatches the event BaseComponentActionCalled for 'module-action'.
     *
     * @param  string  $action
     * @param  mixed   $itemId  also array is allowed
     *
     * @return void
     */
    #[On('module-action')]
    public function moduleAction(string $action, mixed $itemId): void
    {
        BaseComponentActionCalled::dispatch($this, $action, $itemId);
    }

    /**
     * Stable key for wire:key/key() of livewire children.
     *
     * @param  string  $suffix
     *
     * @return string
     */
    public function getStableKey(string $suffix = ''): string
    {
        return md5(static::class.$this->getName().$this->getId()).$suffix;
    }

    /**
     * Livewire hook after a property was updated. Empty by default.
     *
     * @param  string  $property
     * @param  mixed   $value
     *
     * @return void
     */
    public function updated(string $property, mixed $value): void
    {
    }

}