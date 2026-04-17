<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Tests\TestCase;

class SettingsCpAccessTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    private function createSuperAdmin(): \Statamic\Contracts\Auth\User
    {
        $user = User::make()
            ->id('super-admin-test')
            ->email('super@example.com')
            ->makeSuper();

        $user->save();

        return $user;
    }

    private function createRegularUser(): \Statamic\Contracts\Auth\User
    {
        $user = User::make()
            ->id('regular-user-test')
            ->email('regular@example.com');

        $user->save();

        return $user;
    }

    public function test_super_admin_can_access_settings_page(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user, 'web')->get(cp_route('ai-gateway.settings.index'));

        $response->assertStatus(200);
    }

    public function test_non_super_admin_gets_403(): void
    {
        // A non-super user without CP access gets redirected by Statamic's
        // Authorize middleware before reaching the controller.
        $user = $this->createRegularUser();

        $response = $this->actingAs($user, 'web')->get(cp_route('ai-gateway.settings.index'));

        // Statamic's CP middleware redirects unauthorized users
        $response->assertRedirect();
    }

    public function test_unauthenticated_user_gets_redirected_to_login(): void
    {
        $response = $this->get(cp_route('ai-gateway.settings.index'));

        $response->assertRedirect();
    }
}
