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
 * "The President" is the pieces carrying the Azerbaijani state emblem: the
 * eight-pointed star with the flame at its centre, over oak and wheat. Azerbaijani
 * has no article, so the AZ names stay "Prezident" rather than gaining one.
 *
 * The copy here describes material, construction and use. It deliberately does
 * not claim card counts or dimensions: those are not readable from a
 * photograph, and a spec a customer can measure and find wrong is worse than no
 * spec at all. Add them to `specs` once someone has a piece in hand.
 */
final class WalletCatalogue
{
    /**
     * Prefix => product. `a` and `a_` are different products — the underscore
     * is part of the key, not a separator to be trimmed.
     *
     * @return array<string, array{name: array{en:string,az:string}, category: ProductCategory, price: int, leather: array{en:string,az:string}, description: array{en:string,az:string}}>
     */
    public static function all(): array
    {
        // Shared per family, so the colourways of one piece read identically
        // rather than drifting apart word by word.
        $doppKit = [
            'en' => 'A compact wash bag in crazy horse leather, sized for a razor, a brush and a week away. The zip runs the full width so the bag opens flat, and the leather takes marks and darkens with handling rather than wearing out.',
            'az' => 'Crazy horse dərisindən kompakt səyahət çantası — ülgüc, fırça və bir həftəlik yol üçün. Zəncirbənd bütün eni boyu açılır, çanta tam açıq qalır; dəri isə istifadə ilə köhnəlmir, tündləşir və öz izini saxlayır.',
        ];

        $presidentialDoppKit = [
            'en' => 'The dopp kit carrying the Azerbaijani state emblem — the eight-pointed star and flame over oak and wheat — blind-embossed into the front panel by hand, without ink or foil. Same crazy horse leather and full-width zip as the plain kit.',
            'az' => 'Azərbaycanın dövlət gerbini — palıd və sünbül üzərində səkkizguşəli ulduz və alov — daşıyan səyahət çantası. Naxış ön üzə əllə, boya və folqa olmadan basılıb. Dəri və zəncirbənd adi modeldəki kimidir.',
        ];

        $presidentialBifold = [
            'en' => 'A full-grain bifold carrying the Azerbaijani state emblem, blind-embossed by hand into the front panel. Card slots on both wings, a full-length note compartment behind them, and edges painted and burnished by hand so they do not fray.',
            'az' => 'Azərbaycanın dövlət gerbi əllə, boyasız basılmış tam dənəli dəri cüzdan. Hər iki qanadda kart yuvaları, arxasında tam uzunluqda pul bölməsi; kənarları əllə boyanıb və cilalanıb ki, açılmasın.',
        ];

        $snapBifold = [
            'en' => 'A bifold that closes on a snap tab rather than staying open — it keeps its shape in a pocket even when it is full. Card slots inside, a note compartment behind, and hand-painted edges.',
            'az' => 'Düymə ilə bağlanan cüzdan: dolu olanda belə cibdə formasını saxlayır. İçəridə kart yuvaları, arxasında pul bölməsi, kənarları əllə boyanıb.',
        ];

        $flapBifold = [
            'en' => 'A compact bifold with a flap over the card slots, cut small enough for a front pocket. Full-grain leather, saddle-stitched, edges painted by hand.',
            'az' => 'Kart yuvalarının üstündə qapağı olan kompakt cüzdan — ön cibə sığacaq ölçüdə. Tam dənəli dəri, yəhər tikişi, kənarları əllə boyanıb.',
        ];

        $snapCardCase = [
            'en' => 'A card case that closes on two snaps, for the cards you actually carry rather than all of them. Crazy horse leather, which scuffs light and darkens where it is handled.',
            'az' => 'İki düymə ilə bağlanan kart qabı — hamısını yox, gündəlik istifadə etdiyiniz kartlar üçün. Crazy horse dəri: cızıqlar açıq rəngdə qalır, əl dəyən yerlər isə tündləşir.',
        ];

        $flapCardCase = [
            'en' => 'A slim card case with a flap that tucks closed, no hardware to catch on a lining. Hand-cut and saddle-stitched, with painted edges.',
            'az' => 'Qapağı içəri keçən nazik kart qabı — astara ilişəcək heç bir metal detal yoxdur. Əllə kəsilib, yəhər tikişi ilə tikilib, kənarları boyanıb.',
        ];

        $signatureCardHolder = [
            'en' => 'An open-top card holder in crazy horse leather with PULORA blind-embossed into the front — pressed into the hide by hand, no ink and no foil. Slots on both faces and a centre pocket, rounded at the corners so it does not wear through a pocket lining.',
            'az' => 'Crazy horse dərisindən üstü açıq kart qabı; ön üzündə PULORA adı boyasız və folqasız, əllə basılıb. Hər iki üzündə yuvalar, ortada cib; küncləri yumrudur ki, cibin astarını deşməsin.',
        ];

        $slimCardHolder = [
            'en' => 'The same open-top card holder cut from smooth full-grain leather rather than crazy horse — sharper at the corners, and it keeps an even colour instead of darkening where it is handled. PULORA blind-embossed into the front, a slip pocket behind for folded notes.',
            'az' => 'Eyni üstü açıq kart qabı, crazy horse yerinə hamar tam dənəli dəridən: künclər daha dəqiq, rəng isə əl dəydikcə tündləşmir, bərabər qalır. Ön üzdə boyasız basılmış PULORA, arxada qatlanmış pul üçün cib.',
        ];

        $cardPouch = [
            'en' => 'A small flap pouch on a single snap, for cards, a folded note or a key. The lightest thing in the collection.',
            'az' => 'Tək düyməli kiçik qapaqlı cib — kartlar, qatlanmış pul və ya açar üçün. Kolleksiyanın ən yüngül parçası.',
        ];

        $snapMoneyClip = [
            'en' => 'Cards on one side, folded notes held under a snap band on the other. It carries a full wallet in about half the thickness.',
            'az' => 'Bir tərəfdə kartlar, digər tərəfdə düyməli lent altında qatlanmış pullar. Tam cüzdanın daşıdığını təxminən yarı qalınlıqda daşıyır.',
        ];

        $slimMoneyClip = [
            'en' => 'A sprung steel clip set into a leather sleeve — notes under the clip, a few cards in the pocket, and nothing else. About as thin as a wallet gets.',
            'az' => 'Dəri qabın içinə yerləşdirilmiş yaylı polad sıxac: sıxacın altında pullar, cibdə bir neçə kart, artıq heç nə. Cüzdanın ola biləcəyi ən nazik forma.',
        ];

        $documentHolder = [
            'en' => 'A zip folio for a passport, tickets and the papers that travel with them, with a clip so it can hang off a bag rather than get lost inside one.',
            'az' => 'Pasport, biletlər və onlarla birlikdə gedən sənədlər üçün zəncirbəndli qovluq. Karabini var ki, çantanın içində itməsin, kənarından assın.',
        ];

        $crazyHorse = ['en' => 'Crazy horse leather', 'az' => 'Crazy horse dəri'];
        $fullGrain = ['en' => 'Full-grain leather', 'az' => 'Tam dənəli dəri'];

        return [
            // --- Dopp kits. Plain pair first, then the two carrying the emblem.
            'a_' => ['name' => ['en' => 'Voyager Dopp Kit — Walnut', 'az' => 'Voyager Səyahət Çantası — Qəhvəyi'], 'category' => ProductCategory::Bag, 'price' => 7500, 'leather' => $crazyHorse, 'description' => $doppKit],
            'b_' => ['name' => ['en' => 'Voyager Dopp Kit — Black', 'az' => 'Voyager Səyahət Çantası — Qara'], 'category' => ProductCategory::Bag, 'price' => 7500, 'leather' => $crazyHorse, 'description' => $doppKit],
            'd_' => ['name' => ['en' => 'The President Dopp Kit — Walnut', 'az' => 'Prezident Səyahət Çantası — Qəhvəyi'], 'category' => ProductCategory::Bag, 'price' => 8500, 'leather' => $crazyHorse, 'description' => $presidentialDoppKit],
            'c_' => ['name' => ['en' => 'The President Dopp Kit — Black', 'az' => 'Prezident Səyahət Çantası — Qara'], 'category' => ProductCategory::Bag, 'price' => 8500, 'leather' => $crazyHorse, 'description' => $presidentialDoppKit],

            // --- The emblem bifold, in its three colours.
            'g' => ['name' => ['en' => 'The President Bifold — Cognac', 'az' => 'Prezident Cüzdanı — Konyak'], 'category' => ProductCategory::Wallet, 'price' => 5500, 'leather' => $fullGrain, 'description' => $presidentialBifold],
            'f' => ['name' => ['en' => 'The President Bifold — Black', 'az' => 'Prezident Cüzdanı — Qara'], 'category' => ProductCategory::Wallet, 'price' => 5500, 'leather' => $fullGrain, 'description' => $presidentialBifold],
            'h' => ['name' => ['en' => 'The President Bifold — Oxblood', 'az' => 'Prezident Cüzdanı — Tünd Qırmızı'], 'category' => ProductCategory::Wallet, 'price' => 5500, 'leather' => $fullGrain, 'description' => $presidentialBifold],

            // --- Snap bifolds.
            'j' => ['name' => ['en' => 'Snap Bifold — Walnut', 'az' => 'Düyməli Cüzdan — Qəhvəyi'], 'category' => ProductCategory::Wallet, 'price' => 7900, 'leather' => $crazyHorse, 'description' => $snapBifold],
            'c' => ['name' => ['en' => 'Snap Bifold — Oxblood', 'az' => 'Düyməli Cüzdan — Tünd Qırmızı'], 'category' => ProductCategory::Wallet, 'price' => 7900, 'leather' => $fullGrain, 'description' => $snapBifold],
            'u' => ['name' => ['en' => 'Flap Bifold — Cognac', 'az' => 'Qapaqlı Cüzdan — Konyak'], 'category' => ProductCategory::Wallet, 'price' => 6900, 'leather' => $fullGrain, 'description' => $flapBifold],

            // --- The twin-snap card case, in its three colours.
            'x' => ['name' => ['en' => 'Snap Card Case — Walnut', 'az' => 'Düyməli Kart Qabı — Qəhvəyi'], 'category' => ProductCategory::Card, 'price' => 4900, 'leather' => $crazyHorse, 'description' => $snapCardCase],
            'v' => ['name' => ['en' => 'Snap Card Case — Black', 'az' => 'Düyməli Kart Qabı — Qara'], 'category' => ProductCategory::Card, 'price' => 4900, 'leather' => $crazyHorse, 'description' => $snapCardCase],
            'a' => ['name' => ['en' => 'Snap Card Case — Teal', 'az' => 'Düyməli Kart Qabı — Firuzəyi'], 'category' => ProductCategory::Card, 'price' => 4900, 'leather' => $crazyHorse, 'description' => $snapCardCase],

            // --- Flap card cases.
            'y' => ['name' => ['en' => 'Flap Card Case — Black', 'az' => 'Qapaqlı Kart Qabı — Qara'], 'category' => ProductCategory::Card, 'price' => 4500, 'leather' => $fullGrain, 'description' => $flapCardCase],
            'e' => ['name' => ['en' => 'Flap Card Case — Walnut', 'az' => 'Qapaqlı Kart Qabı — Qəhvəyi'], 'category' => ProductCategory::Card, 'price' => 4500, 'leather' => $crazyHorse, 'description' => $flapCardCase],
            'd' => ['name' => ['en' => 'Flap Card Pouch — Tan', 'az' => 'Qapaqlı Kart Cibi — Sarı'], 'category' => ProductCategory::Card, 'price' => 3900, 'leather' => $fullGrain, 'description' => $cardPouch],

            // --- The open-top holders embossed with the maker's name. Two
            //     shapes, two colours each, each pair kept side by side.
            'a__' => ['name' => ['en' => 'Signature Card Holder — Walnut', 'az' => 'İmza Kart Qabı — Qəhvəyi'], 'category' => ProductCategory::Card, 'price' => 2500, 'leather' => $crazyHorse, 'description' => $signatureCardHolder],
            'b__' => ['name' => ['en' => 'Signature Card Holder — Black', 'az' => 'İmza Kart Qabı — Qara'], 'category' => ProductCategory::Card, 'price' => 2500, 'leather' => $crazyHorse, 'description' => $signatureCardHolder],
            'c__' => ['name' => ['en' => 'Slim Card Holder — Walnut', 'az' => 'Nazik Kart Qabı — Qəhvəyi'], 'category' => ProductCategory::Card, 'price' => 2500, 'leather' => $fullGrain, 'description' => $slimCardHolder],
            'd__' => ['name' => ['en' => 'Slim Card Holder — Black', 'az' => 'Nazik Kart Qabı — Qara'], 'category' => ProductCategory::Card, 'price' => 2500, 'leather' => $fullGrain, 'description' => $slimCardHolder],

            // --- Money clips.
            'm' => ['name' => ['en' => 'Snap Money Clip — Walnut', 'az' => 'Düyməli Pul Sıxacağı — Qəhvəyi'], 'category' => ProductCategory::Card, 'price' => 5500, 'leather' => $crazyHorse, 'description' => $snapMoneyClip],
            'b' => ['name' => ['en' => 'Snap Money Clip — Stone', 'az' => 'Düyməli Pul Sıxacağı — Boz'], 'category' => ProductCategory::Card, 'price' => 5500, 'leather' => ['en' => 'Nubuck leather', 'az' => 'Nubuk dəri'], 'description' => $snapMoneyClip],
            'o' => ['name' => ['en' => 'Slim Money Clip — Walnut', 'az' => 'Nazik Pul Sıxacağı — Qəhvəyi'], 'category' => ProductCategory::Card, 'price' => 4900, 'leather' => $fullGrain, 'description' => $slimMoneyClip],
            't' => ['name' => ['en' => 'Slim Money Clip — Black', 'az' => 'Nazik Pul Sıxacağı — Qara'], 'category' => ProductCategory::Card, 'price' => 4900, 'leather' => $fullGrain, 'description' => $slimMoneyClip],

            // --- Document holders.
            'l' => ['name' => ['en' => 'Document Holder — Walnut', 'az' => 'Sənəd Qabı — Qəhvəyi'], 'category' => ProductCategory::Card, 'price' => 6900, 'leather' => $fullGrain, 'description' => $documentHolder],
            'k' => ['name' => ['en' => 'Document Holder — Black Python', 'az' => 'Sənəd Qabı — Qara Piton'], 'category' => ProductCategory::Card, 'price' => 7500, 'leather' => ['en' => 'Python-embossed leather', 'az' => 'Piton naxışlı dəri'], 'description' => $documentHolder],
        ];
    }
}
