<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reseller_domains', 'registrar')) {
            Schema::table('reseller_domains', function (Blueprint $table) {
                $table->string('registrar', 32)->default('skrime')->after('brand_id');
                $table->boolean('is_sandbox')->default(false)->after('registrar');
                $table->string('realtimeregister_domain_name', 255)->nullable()->after('skrime_id');
            });
        }

        DB::table('reseller_domains')->whereNotNull('skrime_id')->update(['registrar' => 'skrime']);
        DB::table('reseller_domains')->whereNull('skrime_id')->update(['registrar' => 'skrime']);

        if (! Schema::hasColumn('tld_pricelist', 'price_source')) {
            Schema::table('tld_pricelist', function (Blueprint $table) {
                $table->index('brand_id');
            });

            Schema::table('tld_pricelist', function (Blueprint $table) {
                $table->dropUnique(['brand_id', 'tld']);
            });

            Schema::table('tld_pricelist', function (Blueprint $table) {
                $table->string('price_source', 32)->default('skrime')->after('tld');
            });

            DB::table('tld_pricelist')->update(['price_source' => 'skrime']);

            Schema::table('tld_pricelist', function (Blueprint $table) {
                $table->unique(['brand_id', 'tld', 'price_source']);
            });

            Schema::table('tld_pricelist', function (Blueprint $table) {
                $table->dropIndex(['brand_id']);
            });
        }

        if (! Schema::hasTable('tld_sale_routing')) {
            Schema::create('tld_sale_routing', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->string('tld', 64);
                $table->string('sale_registrar', 32);
                $table->timestamps();
                $table->unique(['brand_id', 'tld']);
            });
        }

        $pairs = DB::table('tld_pricelist')
            ->select('brand_id', 'tld')
            ->distinct()
            ->get();

        foreach ($pairs as $row) {
            DB::table('tld_sale_routing')->updateOrInsert(
                ['brand_id' => $row->brand_id, 'tld' => $row->tld],
                ['sale_registrar' => 'skrime', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tld_sale_routing');

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->index('brand_id');
        });

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'tld', 'price_source']);
        });

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->dropColumn('price_source');
        });

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->unique(['brand_id', 'tld']);
        });

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->dropIndex(['brand_id']);
        });

        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->dropColumn(['registrar', 'is_sandbox', 'realtimeregister_domain_name']);
        });
    }
};
