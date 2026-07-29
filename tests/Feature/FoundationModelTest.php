<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\AccountActivationToken;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_activation_token_use_expected_casts_and_relationships(): void
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
        ]);

        $token = AccountActivationToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'foundation-model-test'),
            'expires_at' => now()->addHour(),
            'used_at' => null,
            'created_by' => $user->id,
        ]);

        $token->load([
            'user',
            'creator',
        ]);

        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isPendingActivation());
        $this->assertFalse($user->requiresPasswordChange());
        $this->assertTrue($token->isValid());
        $this->assertTrue($token->user->is($user));
        $this->assertTrue($token->creator->is($user));
    }

    public function test_item_generates_public_id_and_calculates_available_stock(): void
    {
        $category = ItemCategory::query()->create([
            'name' => 'Alat Tulis Kantor',
            'description' => 'Kategori pengujian model fondasi.',
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'name' => 'Buah',
            'symbol' => 'buah',
            'is_active' => true,
        ]);

        $item = Item::query()->create([
            'item_code' => 'ATK-0001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Pulpen',
            'description' => 'Barang pengujian model fondasi.',
            'minimum_stock' => 5,
            'storage_location' => 'Gudang Utama',
            'is_active' => true,
        ]);

        $item->refresh();
        $item->load([
            'category',
            'unit',
        ]);

        $this->assertTrue(Str::isUuid($item->public_id));
        $this->assertSame('public_id', $item->getRouteKeyName());
        $this->assertSame('0.00', $item->current_stock);
        $this->assertSame('0.00', $item->reserved_stock);
        $this->assertSame('0.00', $item->available_stock);
        $this->assertTrue($item->is_low_stock);
        $this->assertTrue($item->category->is($category));
        $this->assertTrue($item->unit->is($unit));
    }
}
