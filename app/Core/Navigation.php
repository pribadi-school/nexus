<?php

declare(strict_types=1);

namespace App\Core;

/** Navigation items for the sidebar and the bottom nav. */
final class Navigation
{
    /** @return list<array{label:string, href:string, icon:string}> */
    public static function main(): array
    {
        $items = [['label' => 'Dashboard', 'href' => '/', 'icon' => 'layout-dashboard']];
        foreach (categories()->all() as $category) {
            $items[] = ['label' => $category->name(), 'href' => $category->path(), 'icon' => $category->icon()];
        }
        $items[] = ['label' => 'Signage', 'href' => '/signage', 'icon' => 'tv-minimal'];
        return $items;
    }
}
