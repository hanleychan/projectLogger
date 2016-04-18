<?php
class Pagination {
    public $numItems;
    public $numItemsPerPage;
    public $currentPage;

    /**
     * Sets up pagination data
     */
    public function __construct($numItems = 0, $numItemsPerPage = 10, $currentPage = 1) {
        $this->numItems = $numItems;
        $this->numItemsPerPage = $numItemsPerPage;

        if($currentPage <= $this->getNumPages() && $currentPage >= 1) {
            $this->currentPage = $currentPage;
        }
        else if($currentPage < 1) {
            $this->currentPage = 1;
        }
        else {
            $this->currentPage = $this->getNumPages();
        }
    }

    /**
     * Returns whether there is a page after the current page
     */
    public function hasNextPage() {
        if($this->currentPage < $this->getNumPages()) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Returns whether there is a page before the current page
     */
    public function hasPrevPage() {
        if($this->currentPage - 1 != 0) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Returns the total number of pages
     */
    public function getNumPages() {
        return ceil($this->numItems / $this->numItemsPerPage);
    }

    public function calculateOffset() {
        return ($this->numItemsPerPage * $this->currentPage) - $this->numItemsPerPage;
    }
}

?>

