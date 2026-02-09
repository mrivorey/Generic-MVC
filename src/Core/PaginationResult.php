<?php

namespace App\Core;

class PaginationResult
{
    public function __construct(
        private array $items,
        private int $currentPage,
        private int $lastPage,
        private int $total,
        private int $perPage,
    ) {}

    public function items(): array
    {
        return $this->items;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function links(string $baseUrl): string
    {
        if ($this->lastPage <= 1) {
            return '';
        }

        $pages = $this->buildPageNumbers();

        $html = '<nav><ul class="pagination">';

        // Previous link
        if ($this->currentPage <= 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->buildUrl($baseUrl, $this->currentPage - 1) . '">&laquo;</a></li>';
        }

        // Page numbers
        foreach ($pages as $page) {
            if ($page === '...') {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            } elseif ($page === $this->currentPage) {
                $html .= '<li class="page-item active"><span class="page-link">' . $page . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $this->buildUrl($baseUrl, $page) . '">' . $page . '</a></li>';
            }
        }

        // Next link
        if ($this->currentPage >= $this->lastPage) {
            $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->buildUrl($baseUrl, $this->currentPage + 1) . '">&raquo;</a></li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }

    private function buildPageNumbers(): array
    {
        if ($this->lastPage <= 7) {
            return range(1, $this->lastPage);
        }

        $pages = [];

        // Always show first page
        $pages[] = 1;

        // Left ellipsis
        if ($this->currentPage > 3) {
            $pages[] = '...';
        }

        // Pages around current
        $start = max(2, $this->currentPage - 1);
        $end = min($this->lastPage - 1, $this->currentPage + 1);

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        // Right ellipsis
        if ($this->currentPage < $this->lastPage - 2) {
            $pages[] = '...';
        }

        // Always show last page
        $pages[] = $this->lastPage;

        return $pages;
    }

    private function buildUrl(string $baseUrl, int $page): string
    {
        $parsed = parse_url($baseUrl);

        $query = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $query['page'] = $page;

        $url = ($parsed['scheme'] ?? 'http') . '://';
        $url .= $parsed['host'] ?? '';
        if (isset($parsed['port'])) {
            $url .= ':' . $parsed['port'];
        }
        $url .= $parsed['path'] ?? '/';
        $url .= '?' . http_build_query($query);

        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}
