<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Packages expire automatically: after `available_until` (last departure date or end of the
 * sales period, WIB) they disappear from the public site. Existing packages get the date read
 * from their itinerary PDFs; an admin can change it at any time.
 */
return new class extends Migration
{
    /** Last departure / end of period per package, taken from each PDF. */
    public const DATES = [
        '6d5n-east-coast-usa-tour' => '2026-12-31', '7d6n-east-coast-usa-tour' => '2026-12-29',
        '5d4n-melbourne-amazing-journey' => '2027-03-31', '5d4n-sydney-grand-explorer' => '2027-03-31',
        '4d3n-essential-dubai-desert-safari' => '2027-04-30', '5d4n-magnificent-dubai-abu-dhabi' => '2027-04-30',
        '4d3n-japan-golf-escape' => '2026-12-31', '5d4n-wonders-of-kyushu' => '2026-12-31',
        '3d2n-penang-delight-kek-lok-si-and-penang-hill' => '2027-03-31', '7d6n-signature-new-zealand' => '2027-03-31',
        '4d3n-philippines-heritage-and-city-escape' => '2026-12-31', '4d3n-wonderful-singapore-night-safari-and-sentosa' => '2026-12-31',
        '6d5n-spain-padel-experience-madrid-and-alicante' => '2026-12-31', '5d4n-taiwan-yilan-leisure-trip' => '2027-03-31',
        '6d5n-discover-western-taiwan-tour' => '2027-03-31', '4d3n-bangkok-jastip-package' => '2026-10-31',
        '4d3n-bangkok-pattaya-dinosaur-park-and-frost-magic' => '2026-10-31', '7d6n-year-end-holiday-3-countries' => '2026-12-23',
        '9d7n-fabulous-3-stan' => '2027-03-15', '9d7n-new-year-sale-complete-australia' => '2026-12-25',
        '6d4n-beijing-exotic-escape' => '2026-11-30', '6d4n-guangzhou-qingyuan-gulong-gorge-go-kart' => '2026-12-29',
        '8d6n-harbin-snow-village-beijing' => '2026-12-18', '8d6n-kunming-jiaozi-snow-mountain' => '2027-01-31',
        '8d6n-yangtze-river-cruise' => '2026-11-29', '11d-east-europe-swiss-jungfraujoch' => '2027-03-08',
        '5d3n-best-deal-hainan' => '2027-03-31', '5d3n-hello-hainan' => '2027-03-31',
        '5d3n-hong-kong-shenzhen-zhuhai-macau' => '2026-12-31', '7d4n-japan-golden-route-shirakawago' => '2027-03-13',
        '8d5n-japan-golden-route-shirakawago-theme-park' => '2027-03-12', '8d5n-new-year-japan-grand-golden-route' => '2026-12-25',
        '7d5n-korea-winter-moments-busan-seoul' => '2026-12-28', '8d7n-complete-winter-holidays-taiwan' => '2026-12-22',
        '10d7n-new-year-turkiye-cappadocia-mt-uludag' => '2026-12-24', '10d7n-turkiye-winter-dream-bosphorus-cruise' => '2026-12-05',
        '6d5n-discover-danang' => '2027-01-01', '9d6n-west-europe-christmas-edition' => '2026-12-24',
        '9d7n-west-europe-special-new-year' => '2026-12-30', '9d7n-umrah-reguler-18-desember-2026' => '2026-12-18',
        '6d4n-year-end-korea-winter-experience' => '2026-12-31', '6d5n-west-coast-usa-tour' => '2026-12-14',
    ];

    public function up(): void
    {
        Schema::table('tours', function (Blueprint $t) {
            $t->date('available_until')->nullable()->after('dates_label')->index();
        });
        foreach (self::DATES as $slug => $date) {
            DB::table('tours')->where('slug', $slug)->whereNull('available_until')->update(['available_until' => $date]);
        }
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $t) {
            $t->dropIndex(['available_until']);
            $t->dropColumn('available_until');
        });
    }
};
