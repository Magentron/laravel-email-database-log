<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddMessageHashId extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::table('email_log', function (Blueprint $table) {
			$table->string('hash', 40)->charset('ascii')->nullable()->after('id')->index();
			$table->string('message_id', 1024)->charset('ascii')->nullable()->after('date');
			$table->dateTime('sent_at')->nullable()->after('message_id');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::table('email_log', function (Blueprint $table) {
			$table->dropColumn([
                'hash',
                'message_id',
            ]);
		});
	}

}
