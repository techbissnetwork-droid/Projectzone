<?php
declare(strict_types=1);

namespace Techbiss\Core;

final class Paginator
{
    public int $page;
    public int $perPage;
    public int $total;
    public int $pages;

    public function __construct(int $page, int $perPage, int $total)
    {
        $this->perPage = max(1, $perPage);
        $this->total   = max(0, $total);
        $this->pages   = max(1, (int) ceil($this->total / $this->perPage));
        $this->page    = min(max(1, $page), $this->pages);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function hasPages(): bool
    {
        return $this->pages > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->offset() + $this->perPage, $this->total);
    }

    /** Page numbers to render, with 0 marking an ellipsis. @return array<int,int> */
    public function window(int $each = 2): array
    {
        if ($this->pages <= 7) {
            return range(1, $this->pages);
        }
        $out = [1];
        $start = max(2, $this->page - $each);
        $end   = min($this->pages - 1, $this->page + $each);
        if ($start > 2) {
            $out[] = 0;
        }
        for ($i = $start; $i <= $end; $i++) {
            $out[] = $i;
        }
        if ($end < $this->pages - 1) {
            $out[] = 0;
        }
        $out[] = $this->pages;
        return $out;
    }

    /** Build a URL for a page, preserving the current query string. */
    public function url(string $base, int $page, array $query = []): string
    {
        $query['page'] = $page;
        $query = array_filter($query, static fn ($v) => $v !== '' && $v !== null && $v !== 0);
        if ($page <= 1) {
            unset($query['page']);
        }
        $qs = http_build_query($query);
        return $base . ($qs !== '' ? '?' . $qs : '');
    }
}
