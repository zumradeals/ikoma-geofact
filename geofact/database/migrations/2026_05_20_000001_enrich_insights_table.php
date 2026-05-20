<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insights', function (Blueprint $table) {
            $table->enum('trend_direction', ['improving', 'stable', 'degrading'])->nullable()->after('confidence_level');
            $table->decimal('risk_score', 3, 1)->nullable()->after('trend_direction');       // 0.0 – 10.0
            $table->string('fleet_position', 30)->nullable()->after('risk_score');             // top_quartile|above_average|average|below_average|bottom_quartile
            $table->json('recommendations')->nullable()->after('fleet_position');              // ["action 1", "action 2"]
            $table->boolean('follow_up_required')->default(false)->after('recommendations');
            $table->tinyInteger('follow_up_days')->unsigned()->nullable()->after('follow_up_required');
        });
    }

    public function down(): void
    {
        Schema::table('insights', function (Blueprint $table) {
            $table->dropColumn([
                'trend_direction', 'risk_score', 'fleet_position',
                'recommendations', 'follow_up_required', 'follow_up_days',
            ]);
        });
    }
};
