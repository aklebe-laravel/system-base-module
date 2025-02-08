<?php

namespace Modules\SystemBase\app\Http\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\SystemBase\app\Events\Livewire\BaseComponentActionCalled;
use Modules\WebsiteBase\app\Services\WebsiteService;
use Throwable;

class BaseComponent extends Component
{
    /**
     * Restrictions to allow this component.
     */
    const array aclResources = [];

    /**
     * Frontend messages.
     *
     * @var array
     */
    public array $baseMessages = [];

    /**
     * Last message time to determine when to reset.
     *
     * @var int
     */
    public int $timeLastMessage = 0;

    /**
     * Lifetime to automatically reset messages.
     *
     * @var int
     */
    public int $messagesLifeTime = 2;

    /**
     * This data depends on parent livewire (if any).
     * Example: If parent is User and the current (this) is a Token, so token can access to parentData.id which is the user id
     * Assign it as parameter in blades.
     *
     * @var array
     */
    public array $parentData = [];

    /**
     * @param $id
     */
    public function __construct($id = null)
    {
        // parent::__construct($id);
        $this->setId($id);

        $this->clearMessages();
    }

    /**
     * Should be overwritten.
     *
     * Runs on every subsequent request, after the component is hydrated, but before an action is performed, or render() is called
     * This doesn't run on the initial request ("mount" does)...
     *
     * @return void
     */
    protected function initHydrate(): void
    {
        // Important but may be replaced! clear messages for next interaction!
        // @todo: place it to booted?
        $this->checkClearMessages();
    }

    /**
     * Runs on every subsequent request, after the component is hydrated, but before an action is performed, or render() is called
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->initHydrate();
    }

    /**
     * Should be overwritten.
     *
     * Runs once, immediately after the component is instantiated, but before render() is called.
     * This is only called once on initial page load and never called again, even on component refreshes
     *
     * @return void
     */
    protected function initMount(): void
    {
    }

    /**
     * Runs once, immediately after the component is instantiated, but before render() is called.
     * This is only called once on initial page load and never called again, even on component refreshes
     *
     * @return void
     */
    public function mount(): void
    {
        $this->initMount();
    }

    /**
     * Runs on every request, after the component is mounted or hydrated, but before any update methods are called
     *
     * @return void
     */
    protected function initBooted(): void
    {
        app(WebsiteService::class)->provideMessageBoxButtons(category: 'default');

        // if messages exists oin session, then 1) assign this messages and 2) delete this session
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
     * Runs on every request, after the component is mounted or hydrated, but before any update methods are called
     *
     * @return void
     */
    public function booted(): void
    {
        $this->initBooted();
    }

    /**
     * Set a message. Use $tempSession for redirects to different livewires.
     *
     * @param  string  $message
     * @param  string  $key
     * @param  bool    $tempSession  if true set message to session
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
     * @return void
     * @internal Refresh in general seems not working without it ...
     */
    #[On('refresh')]
    public function anotherRefresh(): void
    {
    }

    /**
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
     * Can be dispatched.
     *
     * @return void
     */
    #[On('reset-messages')]
    public function resetMessages(): void
    {
        $this->clearMessages();
    }

    /**
     * Call this inside instead of resetMessages()
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
     * @param $livewireId
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
     * Building a string for wire:click="..."
     *
     * @param  string  $methodName
     * @param  array   $params
     * @param  bool    $fistParamId
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
     * @param  mixed   $itemId  also array is allowed
     * @param  string  $action
     *
     * @return void
     */
    #[On('module-action')]
    public function moduleAction(string $action, mixed $itemId): void
    {
        BaseComponentActionCalled::dispatch($this, $action, $itemId);
    }
}