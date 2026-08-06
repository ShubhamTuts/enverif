<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void {
 Schema::create('users', function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->string('locale',5)->default('en');$t->string('theme',10)->default('system');$t->rememberToken();$t->timestamps();});
 Schema::create('workspaces', function(Blueprint $t){$t->id();$t->string('name');$t->string('slug')->unique();$t->string('timezone',64)->default('UTC');$t->string('locale',5)->default('en');$t->json('settings')->nullable();$t->timestamps();});
 Schema::create('workspace_user', function(Blueprint $t){$t->foreignId('workspace_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('role',24)->default('member');$t->timestamps();$t->primary(['workspace_id','user_id']);});
 Schema::create('password_reset_tokens', function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
 Schema::create('sessions', function(Blueprint $t){$t->string('id')->primary();$t->foreignId('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
 } public function down(): void {Schema::dropIfExists('sessions');Schema::dropIfExists('password_reset_tokens');Schema::dropIfExists('workspace_user');Schema::dropIfExists('workspaces');Schema::dropIfExists('users');}};
