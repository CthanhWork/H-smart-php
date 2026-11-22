<?php

namespace Tests\Feature;

use App\Modules\Assistant\Http\Controllers\AssistantController;
use App\Modules\Assistant\Models\AssistantConversation;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssistantOwnershipTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_user_cannot_read_another_users_conversation(): void
    {
        $owner = User::create(['username' => 'conversation-owner', 'email' => 'owner@example.test', 'password_hash' => 'unused']);
        $stranger = User::create(['username' => 'conversation-stranger', 'email' => 'stranger@example.test', 'password_hash' => 'unused']);
        $conversation = AssistantConversation::create(['user_id' => $owner->id, 'title' => 'Riêng tư']);
        AssistantMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Nội dung riêng tư']);
        $request = Request::create('/api/v1/assistant/conversations/'.$conversation->id, 'GET');
        $request->setUserResolver(fn () => $stranger);

        try {
            app(AssistantController::class)->show($request, (int) $conversation->id);
            $this->fail('A different user read a private conversation.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame([AssistantConversation::class], $exception->getModel() ? [$exception->getModel()] : []);
        }
    }
}
