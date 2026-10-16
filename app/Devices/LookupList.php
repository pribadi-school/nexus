<?php

declare(strict_types=1);

namespace App\Devices;

/**
 * An editable list of names that devices point to: rooms (StudyTouch), areas
 * (CCTV). One generic page manages every list; a
 * category declares its lists in Category::lists().
 *
 * The table needs: id, name (unique), sort_order, created_at.
 */
final class LookupList
{
    /**
     * @param string $usageSql  SELECT returning one row per list item in use:
     *                          columns `id` and `n` (devices not deleted)
     * @param array{0:string, 1:string} $usageNoun  ['board', 'boards']
     */
    public function __construct(
        public readonly string $table,
        public readonly string $path,
        public readonly string $title,
        public readonly string $singular,
        public readonly string $intro,
        public readonly Category $category,
        public readonly string $usageSql,
        public readonly array $usageNoun,
        public readonly int $maxLength = 80,
    ) {
    }
}
