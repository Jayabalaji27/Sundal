    <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_template_langs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->string('lang');
            $table->string('title');
            $table->longText('content');
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('notification_templates')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_template_langs');
    }
};