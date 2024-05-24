<?php

namespace Modules\SystemBase\app\Http\Livewire;

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
    public int $itemsPerPage = 15;

    /**
     * Maximal count of all pages available (all items / $itemsPerPage)
     *
     * @var int
     */
    public int $maxPages = 1;

    /**
     * The current page index
     *
     * @var int
     */
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
     * @return void
     */
    protected function calcNavigationParameters() : void
    {
        //
        if ($this->currentPage > $this->maxPages) {
            $this->currentPage = $this->maxPages;
        }

        //
        if ($this->loopMax > $this->maxPages) {
            $this->loopMax = $this->maxPages;
        }
        $this->loopEnd = $this->loopMax;
        if ($this->maxPages > $this->loopMax) {
            $endIndex = $this->currentPage + floor($this->loopMax / 2);
            if ($endIndex > $this->loopEnd) {
                $this->loopEnd = $endIndex;
                if ($endIndex > $this->maxPages) {
                    $this->loopEnd = $this->maxPages;
                }
                $this->loopStart = ($this->loopEnd - $this->loopMax) + 1;
            }
        }

        //
        $this->prevPage = $this->currentPage - 1;
        if ($this->prevPage < 1) {
            $this->prevPage = 1;
        }
        $this->nextPage = $this->currentPage + 1;
        if ($this->nextPage > $this->loopEnd) {
            $this->nextPage = $this->loopEnd;
        }

    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        // Should be placed elsewhere like in initHydrate(), but didn't work there.
        $this->calcNavigationParameters();

        //
        return view('components.pagination');
    }

}
