<?php

namespace Modules\SystemBase\app\Http\Livewire;

use Livewire\Attributes\Reactive;

/**
 * Pagination component (view 'components.pagination').
 * Set $maxPages, $currentPage and optionally $pageLink or $pageLinkWire,
 * the navigation values are calculated before rendering.
 *
 * $maxPages, $currentPage and $itemsPerPage are reactive: a parent component (like a data table)
 * passes new values on every render, otherwise the pagination would keep the values of the first render.
 */
class Pagination extends BaseComponent
{
    /**
     * Show the "..." placeholder
     *
     * @var bool
     */
    public bool $showContinueMark = true;

    /**
     * Show the "<<" and ">>" buttons
     *
     * @var bool
     */
    public bool $showStartEndNavigation = true;

    /**
     * Show infos like page 42 of 104
     *
     * @var bool
     */
    public bool $showInfo = true;

    /**
     * Items/rows per page
     *
     * @var int
     */
    #[Reactive]
    public int $itemsPerPage = 15;

    /**
     * Maximal count of all pages available (all items / $itemsPerPage)
     *
     * @var int
     */
    #[Reactive]
    public int $maxPages = 1;

    /**
     * The current page index
     *
     * @var int
     */
    #[Reactive]
    public int $currentPage = 1;

    /**
     *  Usually $currentPage - 1
     *
     * @var int
     */
    public int $prevPage = 1;

    /**
     *  Usually $currentPage + 1
     *
     * @var int
     */
    public int $nextPage = 1;

    /**
     * Leave empty if no links wanted.
     *
     * @var string
     */
    public string $pageLink = ''; // '/?page=%d';

    /**
     * Leave empty if no wire is wanted.
     *
     * @var string
     */
    public string $pageLinkWire = ''; // '$dispatch("set-pagination-current-page", {"collectionName":"%s", "index":"%d"})'

    /**
     * Maximal page buttons shown to navigate.
     *
     * @var int
     */
    public int $loopMax = 7;

    /**
     * Start index for shown page navigation buttons.
     *
     * @var int
     */
    public int $loopStart = 1;

    /**
     * Last index for shown page navigation buttons.
     *
     * @var int
     */
    public int $loopEnd = 0;

    /**
     * Calculates $loopStart, $loopEnd, $prevPage and $nextPage.
     * The shown page buttons are centered around the current page if possible.
     *
     * @return void
     */
    protected function calcNavigationParameters(): void
    {
        // reactive properties must not be changed here, so work with local values
        $maxPages = max(1, $this->maxPages);
        $current = min(max(1, $this->currentPage), $maxPages);
        $loopMax = max(1, min($this->loopMax, $maxPages));

        // shown buttons centered around the current page, shifted at the start and the end
        $this->loopStart = max(1, min($current - intdiv($loopMax, 2), $maxPages - $loopMax + 1));
        $this->loopEnd = $this->loopStart + $loopMax - 1;

        $this->prevPage = max(1, $current - 1);
        $this->nextPage = min($maxPages, $current + 1);
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        // Should be placed elsewhere like in initHydrate(), but didn't work there.
        $this->calcNavigationParameters();

        return view('components.pagination');
    }

}
