<?php
/**
 * core/Pagination.php
 * Advanced pagination helper
 */

class Pagination {
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $baseUrl;
    
    public function __construct($totalItems, $itemsPerPage = 20, $currentPage = 1, $baseUrl = '') {
        $this->totalItems = (int)$totalItems;
        $this->itemsPerPage = (int)$itemsPerPage;
        $this->currentPage = max(1, (int)$currentPage);
        $this->baseUrl = $baseUrl;
    }
    
    public function getOffset() {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    public function getLimit() {
        return $this->itemsPerPage;
    }
    
    public function getTotalPages() {
        return max(1, ceil($this->totalItems / $this->itemsPerPage));
    }
    
    public function hasNext() {
        return $this->currentPage < $this->getTotalPages();
    }
    
    public function hasPrev() {
        return $this->currentPage > 1;
    }
    
    public function render($class = 'pagination') {
        $totalPages = $this->getTotalPages();
        if ($totalPages <= 1) {
            return '';
        }
        
        $html = '<nav><ul class="' . $class . '">';
        
        // Previous
        if ($this->hasPrev()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->getPageUrl($this->currentPage - 1) . '">Previous</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
        }
        
        // Page numbers
        $start = max(1, $this->currentPage - 2);
        $end = min($totalPages, $this->currentPage + 2);
        
        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->getPageUrl(1) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $this->currentPage) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $this->getPageUrl($i) . '">' . $i . '</a></li>';
            }
        }
        
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->getPageUrl($totalPages) . '">' . $totalPages . '</a></li>';
        }
        
        // Next
        if ($this->hasNext()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->getPageUrl($this->currentPage + 1) . '">Next</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
        }
        
        $html .= '</ul></nav>';
        return $html;
    }
    
    private function getPageUrl($page) {
        $url = $this->baseUrl ?: $_SERVER['REQUEST_URI'];
        $url = preg_replace('/[?&]page=\d+/', '', $url);
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . 'page=' . $page;
    }
    
    public function getInfo() {
        $start = $this->getOffset() + 1;
        $end = min($this->getOffset() + $this->itemsPerPage, $this->totalItems);
        return [
            'current_page' => $this->currentPage,
            'total_pages' => $this->getTotalPages(),
            'total_items' => $this->totalItems,
            'items_per_page' => $this->itemsPerPage,
            'start' => $start,
            'end' => $end,
            'showing' => "Showing {$start} to {$end} of {$this->totalItems} results"
        ];
    }
}

