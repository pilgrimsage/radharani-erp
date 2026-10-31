<?php

namespace Tests\Feature\Movement;

use App\Livewire\Layout\GlobalSearch;
use App\Livewire\Sales\NewSale;
use App\Livewire\Stock\AssignToContainer;
use App\Livewire\Stock\ItemForm;
use App\Models\Customer\Customer;
use App\Models\Movement\Movement;
use App\Models\Movement\RateLog;
use App\Models\Pricing\PricingRule;
use App\Models\Pricing\TaxSetting;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\Stock\QrCode;
use App\Models\User;
use App\Rules\HuidFree;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 31 October: the top-bar search finds what the Inventory table finds and shows both codes of a piece; a HUID held only by
 * a deleted piece no longer blocks a new one (and the message names the piece when a live one does); the bill text, printed
 * bill and invoice carry the customer's address and who made the bill.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=Oct31Test
 */
class Oct31Test extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
        $this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.');
        $this->actingAs($this->user);
    }

    private function piece(array $extra = []): Item
    {
        return Item::create($extra + ['packet_id' => $this->packetId(), 'internal_code' => 'OT' . random_int(100, 999), 'metal' => 'gold', 'category' => 'Ring',
            'purity' => '9K', 'making_value' => 0, 'weight' => 5, 'status' => 'in_stock']);
    }

    private function search(string $q)
    {
        return Livewire::test(GlobalSearch::class)->set('query', $q);
    }

    // ------------------------------------------------------------------ top-bar search

    public function test_a_piece_with_both_codes_is_found_by_either_and_shows_both(): void
    {
        $item = $this->piece(['huid_code' => 'EGMQ' . random_int(10, 99), 'internal_code' => '9X3' . random_int(10, 99)]);

        $this->assertSame("{$item->huid_code} (tag {$item->internal_code})", $item->label_with_tag);
        $this->assertSame($item->huid_code, $item->label, 'label itself is unchanged');

        foreach ([$item->huid_code, $item->internal_code, strtolower($item->internal_code)] as $q) {
            $this->search($q)->assertSee($item->huid_code)->assertSee('Tag ' . $item->internal_code);
        }
    }

    public function test_the_top_bar_finds_what_the_table_finds_description_packet_and_sticker(): void
    {
        $packet = Packet::create(['code' => 'ZQPK' . random_int(1000, 9999)]);
        $item = $this->piece(['packet_id' => $packet->id, 'description' => 'Zqdesign temple ruby drops']);
        $sticker = QrCode::forTarget('item', $item->id);

        $this->search('Zqdesign')->assertSee($item->label);
        $this->search($packet->code)->assertSee($item->label);
        $this->search($sticker->code)->assertSee($item->label);
        $this->search('ZZNOPE' . random_int(100, 999))->assertSee('No matches');
    }

    public function test_an_exact_code_comes_before_a_crowd_of_category_matches(): void
    {
        $cat = 'Zqcat' . random_int(100, 999);
        foreach (range(1, 7) as $n) {
            $this->piece(['category' => $cat, 'internal_code' => 'ZC' . $n . random_int(10, 99)]);
        }
        $target = $this->piece(['category' => 'Chain', 'huid_code' => 'ZQ' . random_int(1000, 9999)]);

        $rows = $this->search($target->huid_code)->instance()->results['items'];
        $this->assertSame($target->id, $rows->first()->id);

        // a category word that fills more than five rows still shows five, not a crash
        $this->assertCount(5, $this->search($cat)->instance()->results['items']);
    }

    public function test_a_deleted_piece_is_not_in_the_top_bar(): void
    {
        $item = $this->piece(['huid_code' => 'ZD' . random_int(1000, 9999)]);
        $item->delete();

        $this->search($item->huid_code)->assertSee('No matches');
    }

    // ------------------------------------------------------------------ HUID

    public function test_a_live_piece_blocks_the_huid_and_the_message_names_it(): void
    {
        $item = $this->piece(['huid_code' => 'ZH' . random_int(1000, 9999), 'category' => 'Necklace', 'weight' => 16.06]);
        $msg = $this->huidMessage($item->huid_code);

        $this->assertStringContainsString("The HUID {$item->huid_code} is already on another piece", $msg);
        $this->assertStringContainsString('Necklace 16.060 g', $msg);
        $this->assertStringContainsString("tag {$item->internal_code}", $msg);
        $this->assertStringContainsString('in stock', $msg);
        $this->assertNotNull($this->huidMessage(strtolower($item->huid_code)), 'case does not matter');
    }

    public function test_the_piece_being_edited_does_not_block_its_own_huid(): void
    {
        $item = $this->piece(['huid_code' => 'ZE' . random_int(1000, 9999)]);
        $this->assertNull($this->huidMessage($item->huid_code, $item->id));
        $this->assertNotNull($this->huidMessage($item->huid_code, $item->id + 1));
    }

    public function test_a_deleted_piece_does_not_block_its_huid_and_blank_is_fine(): void
    {
        $item = $this->piece(['huid_code' => 'ZX' . random_int(1000, 9999)]);
        $item->delete();

        $this->assertNull($this->huidMessage($item->huid_code));
        $this->assertNull($this->huidMessage(''));
        $this->assertNull($this->huidMessage('   '));
    }

    public function test_the_item_form_uses_it_for_the_piece_and_the_partner(): void
    {
        $live = $this->piece(['huid_code' => 'ZF' . random_int(1000, 9999)]);
        $gone = $this->piece(['huid_code' => 'ZG' . random_int(1000, 9999)]);
        $gone->delete();

        Livewire::test(ItemForm::class)->set('huid_code', $live->huid_code)->call('save')->assertHasErrors('huid_code');
        Livewire::test(ItemForm::class)->set('huid_code', $gone->huid_code)->call('save')->assertHasNoErrors('huid_code');
        Livewire::test(ItemForm::class)->set('huid_code', 'ZH' . random_int(100, 999))->set('partnerHuid', $live->huid_code)->call('save')->assertHasErrors('partnerHuid');
    }

    public function test_a_new_piece_can_be_saved_with_the_huid_of_a_deleted_one(): void
    {
        $gone = $this->piece(['huid_code' => 'ZK' . random_int(1000, 9999)]);
        $gone->delete();

        $cat = \App\Models\Stock\ItemCategory::firstOrCreate(['metal' => 'gold', 'name' => 'Ring']);
        Livewire::test(ItemForm::class)->set('metal', 'gold')->set('categoryId', $cat->id)->set('purity', '22K')->set('weight', '3.2')
            ->set('making_type', 'percentage')->set('making_value', '10')->set('packet_id', $this->packetId())->set('huid_code', $gone->huid_code)
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1, Item::where('huid_code', $gone->huid_code)->count(), 'one live piece with it');
    }

    private function huidMessage(string $value, ?int $ignore = null): ?string
    {
        $msg = null;
        (new HuidFree($ignore))->validate('huid_code', $value, function ($m) use (&$msg) {
            $msg = $m;
        });

        return $msg;
    }

    // ------------------------------------------------------------------ scan list

    public function test_change_location_names_a_scanned_piece_with_its_tag_code(): void
    {
        $packet = Packet::create(['code' => 'ZP' . random_int(10000, 99999)]);
        $item = $this->piece(['huid_code' => 'ZS' . random_int(1000, 9999), 'internal_code' => 'TG' . random_int(100, 999)]);

        $c = Livewire::test(AssignToContainer::class)->call('scan', $item->internal_code);
        $this->assertSame($item->label_with_tag, $c->get('tray')[0]['code']);
        $c->call('scan', $item->internal_code)->assertSet('feedback.message', 'This item is already scanned.')->assertSet('feedback.code', $item->label_with_tag);
    }

    // ------------------------------------------------------------------ bill

    private function saleFor(Customer $customer): Sale
    {
        TaxSetting::current()->update(['jewellery_percent' => 3, 'hallmark_percent' => 18]);
        RateLog::create(['metal' => 'gold', 'purity' => '9K', 'rate' => 1000, 'source' => 'manual', 'updated_by' => $this->user->id]);
        PricingRule::whereIn('kind', ['making', 'additional', 'discount', 'hallmark'])->update(['active' => false]);
        app(\App\Services\PricingService::class)->forgetRules();

        $item = $this->piece();
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $item->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);
        Livewire::test(NewSale::class)->call('addItem', $item->id)->call('next')->call('next')->call('chooseCustomer', $customer->id)->call('next')->call('next')->call('submit')->assertHasNoErrors();

        return Sale::latest('id')->first();
    }

    public function test_the_copied_bill_has_the_address_and_who_billed_it(): void
    {
        $customer = Customer::create(['name' => 'Addr Buyer', 'phone' => (string) random_int(6000000000, 9999999999), 'address' => "Adorpara,\n  Hooghly"]);
        TaxSetting::current()->update(['jewellery_percent' => 3, 'hallmark_percent' => 18]);
        RateLog::create(['metal' => 'gold', 'purity' => '9K', 'rate' => 1000, 'source' => 'manual', 'updated_by' => $this->user->id]);
        $item = $this->piece();
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $item->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);

        $text = Livewire::test(NewSale::class)->call('addItem', $item->id)->call('next')->call('next')->call('chooseCustomer', $customer->id)->call('next')->call('next')
            ->assertSet('step', 5)->viewData('copyText');

        $this->assertStringContainsString('Address: Adorpara, Hooghly', $text, 'on one line');
        $this->assertStringContainsString('Billed by: ' . $this->user->name, $text);
        $this->assertLessThan(strpos($text, $item->label), strpos($text, 'Address:'), 'before the pieces');
    }

    public function test_the_copied_bill_leaves_the_address_out_for_the_walk_in_and_when_empty(): void
    {
        $item = $this->piece();
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $item->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);
        $walk = Livewire::test(NewSale::class)->call('addItem', $item->id)->call('next')->call('next')->call('chooseCustomer', Customer::walkIn()->id)->call('next')->call('next')->viewData('copyText');
        $this->assertStringNotContainsString('Address:', $walk);
        $this->assertStringContainsString('Billed by:', $walk);

        $noAddr = Customer::create(['name' => 'No Addr', 'phone' => (string) random_int(6000000000, 9999999999)]);
        $item2 = $this->piece();
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $item2->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);
        $text = Livewire::test(NewSale::class)->call('addItem', $item2->id)->call('next')->call('next')->call('chooseCustomer', $noAddr->id)->call('next')->call('next')->viewData('copyText');
        $this->assertStringNotContainsString('Address:', $text);
    }

    public function test_the_printed_bill_and_the_invoice_show_the_address_and_the_staff_name(): void
    {
        $customer = Customer::create(['name' => 'Print Buyer', 'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Adorpara, Hooghly 712121']);
        $sale = $this->saleFor($customer);

        $this->get(route('sales.bill', $sale))->assertOk()->assertSee('Adorpara, Hooghly 712121')->assertSee('Billed by ' . $this->user->name);
        $this->get(route('sales.invoice', $sale))->assertOk()->assertSee('Adorpara, Hooghly 712121')->assertSee('Entered by');

        $walk = $this->saleFor(Customer::walkIn());
        $this->get(route('sales.bill', $walk))->assertOk()->assertSee('Billed by')->assertDontSee('Adorpara');
    }

    public function test_the_payment_step_puts_the_amount_on_its_own_full_width_row(): void
    {
        $customer = Customer::create(['name' => 'Pay Row', 'phone' => (string) random_int(6000000000, 9999999999)]);
        RateLog::create(['metal' => 'gold', 'purity' => '9K', 'rate' => 1000, 'source' => 'manual', 'updated_by' => $this->user->id]);
        $item = $this->piece();
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $item->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);

        $c = Livewire::test(NewSale::class)->call('addItem', $item->id)->call('next')->call('next')->call('chooseCustomer', $customer->id)->call('next')->assertSet('step', 4);
        $c->assertSeeHtml('col-span-3 order-last')->assertSeeHtml('wire:model.live.debounce.300ms="payments.0.amount"')->assertSee('Rest');
        if ($dump = getenv('RJ_DUMP_HTML')) {
            file_put_contents($dump, $c->html());
        }
    }
}
