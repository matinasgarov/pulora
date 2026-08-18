<?php // database/seeders/WalletCatalogue.php

namespace Database\Seeders;

use App\Domain\Catalog\ProductCategory;

/**
 * What each group of supplier photographs actually is.
 *
 * The photographs are named <prefix><number> — a1, a2, x3 — and everything
 * sharing a prefix is one product shot from several angles. The prefix is the
 * only thing the filenames tell us; this is where it becomes a product with a
 * name, a price and a place in the grid.
 *
 * Order matters. The grid's default sort is the operator's own order, which is
 * insertion order, and the seeder creates products in the order they appear
 * here. So the same piece in three colours sits in three adjacent tiles rather
 * than scattered across two pages — which is the whole reason this list is
 * hand-ordered rather than alphabetical by prefix.
 *
 * "Presidential" is the pieces carrying the Azerbaijani state emblem: the
 * eight-pointed star with the flame at its centre, over oak and wheat.
 */
final class WalletCatalogue
{
    /**
     * Prefix => [en name, az name, category, price in qəpik, en leather, az leather].
     *
     * Prefixes ending in an underscore are the mini dopp kits; the rest are
     * small leather goods. `a` and `a_` are different products — the underscore
     * is part of the key, not a separator to be trimmed.
     *
     * @return array<string, array{0:string,1:string,2:ProductCategory,3:int,4:string,5:string}>
     */
    public static function all(): array
    {
        return [
            // --- Dopp kits. Plain pair first, then the two carrying the emblem.
            'a_' => ['Voyager Dopp Kit — Walnut', 'Voyager Səyahət Çantası — Qoz', ProductCategory::Bag, 14900, 'Crazy horse leather', 'Crazy horse dəri'],
            'b_' => ['Voyager Dopp Kit — Black', 'Voyager Səyahət Çantası — Qara', ProductCategory::Bag, 14900, 'Crazy horse leather', 'Crazy horse dəri'],
            'd_' => ['Presidential Dopp Kit — Walnut', 'Prezident Səyahət Çantası — Qoz', ProductCategory::Bag, 16900, 'Crazy horse leather', 'Crazy horse dəri'],
            'c_' => ['Presidential Dopp Kit — Black', 'Prezident Səyahət Çantası — Qara', ProductCategory::Bag, 16900, 'Crazy horse leather', 'Crazy horse dəri'],

            // --- The emblem bifold, in its three colours.
            'g' => ['Presidential Bifold — Cognac', 'Prezident Cüzdanı — Konyak', ProductCategory::Wallet, 8900, 'Full-grain leather', 'Tam dənəli dəri'],
            'f' => ['Presidential Bifold — Black', 'Prezident Cüzdanı — Qara', ProductCategory::Wallet, 8900, 'Full-grain leather', 'Tam dənəli dəri'],
            'h' => ['Presidential Bifold — Oxblood', 'Prezident Cüzdanı — Tünd Qırmızı', ProductCategory::Wallet, 8900, 'Full-grain leather', 'Tam dənəli dəri'],

            // --- Snap bifolds.
            'j' => ['Snap Bifold — Walnut', 'Düyməli Cüzdan — Qoz', ProductCategory::Wallet, 7900, 'Crazy horse leather', 'Crazy horse dəri'],
            'c' => ['Snap Bifold — Oxblood', 'Düyməli Cüzdan — Tünd Qırmızı', ProductCategory::Wallet, 7900, 'Full-grain leather', 'Tam dənəli dəri'],
            'u' => ['Flap Bifold — Cognac', 'Qapaqlı Cüzdan — Konyak', ProductCategory::Wallet, 6900, 'Full-grain leather', 'Tam dənəli dəri'],

            // --- The twin-snap card case, in its three colours.
            'x' => ['Snap Card Case — Walnut', 'Düyməli Kart Qabı — Qoz', ProductCategory::Card, 4900, 'Crazy horse leather', 'Crazy horse dəri'],
            'v' => ['Snap Card Case — Black', 'Düyməli Kart Qabı — Qara', ProductCategory::Card, 4900, 'Crazy horse leather', 'Crazy horse dəri'],
            'a' => ['Snap Card Case — Teal', 'Düyməli Kart Qabı — Firuzəyi', ProductCategory::Card, 4900, 'Crazy horse leather', 'Crazy horse dəri'],

            // --- Flap card cases.
            'y' => ['Flap Card Case — Black', 'Qapaqlı Kart Qabı — Qara', ProductCategory::Card, 4500, 'Full-grain leather', 'Tam dənəli dəri'],
            'e' => ['Flap Card Case — Walnut', 'Qapaqlı Kart Qabı — Qoz', ProductCategory::Card, 4500, 'Crazy horse leather', 'Crazy horse dəri'],
            'd' => ['Flap Card Pouch — Tan', 'Qapaqlı Kart Cibi — Sarı', ProductCategory::Card, 3900, 'Full-grain leather', 'Tam dənəli dəri'],

            // --- Money clips.
            'm' => ['Snap Money Clip — Walnut', 'Düyməli Pul Sıxacağı — Qoz', ProductCategory::Card, 5500, 'Crazy horse leather', 'Crazy horse dəri'],
            'b' => ['Snap Money Clip — Stone', 'Düyməli Pul Sıxacağı — Boz', ProductCategory::Card, 5500, 'Nubuck leather', 'Nubuk dəri'],
            'o' => ['Slim Money Clip — Walnut', 'Nazik Pul Sıxacağı — Qoz', ProductCategory::Card, 4900, 'Full-grain leather', 'Tam dənəli dəri'],
            't' => ['Slim Money Clip — Black', 'Nazik Pul Sıxacağı — Qara', ProductCategory::Card, 4900, 'Full-grain leather', 'Tam dənəli dəri'],

            // --- Document holders.
            'l' => ['Document Holder — Walnut', 'Sənəd Qabı — Qoz', ProductCategory::Card, 6900, 'Full-grain leather', 'Tam dənəli dəri'],
            'k' => ['Document Holder — Black Python', 'Sənəd Qabı — Qara Piton', ProductCategory::Card, 7500, 'Python-embossed leather', 'Piton naxışlı dəri'],
        ];
    }
}
