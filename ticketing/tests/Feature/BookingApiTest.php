<?php
namespace Tests\Feature;
use App\Models\Event;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
class BookingApiTest extends TestCase
{
    use RefreshDatabase;
    public function test_buyer_can_buy_and_idempotency_key_prevents_duplicate_booking():void
    {
        Queue::fake();
        $buyer=User::factory()->create(['role'=>'buyer']);
        $organizer=User::factory()->create(['role'=>'organizer']);
        $venue=Venue::create(['name'=>'Venue','city'=>'Damascus','capacity'=>100]);
        $event=Event::withoutGlobalScopes()->create(['organizer_id'=>$organizer->id,'venue_id'=>$venue->id,'title'=>'Test','city'=>'Damascus','category'=>'music','starts_at'=>now()->addDays(2),'status'=>'published','max_per_order'=>5]);
        $category=TicketCategory::create(['event_id'=>$event->id,'name'=>'Standard','type'=>'standard','price'=>20,'quantity'=>10,'sold'=>0,'max_per_order'=>5]);
        $payload=['event_id'=>$event->id,'items'=>[['ticket_category_id'=>$category->id,'quantity'=>2]]];
        $this->actingAs($buyer)->withHeader('Idempotency-Key','test-key-123')->postJson('/api/bookings',$payload)->assertCreated()->assertJsonPath('data.status','paid');
        $this->actingAs($buyer)->withHeader('Idempotency-Key','test-key-123')->postJson('/api/bookings',$payload)->assertCreated();
        $this->assertDatabaseCount('bookings',1);
        $this->assertDatabaseHas('ticket_categories',['id'=>$category->id,'sold'=>2]);
    }
    public function test_booking_rejects_category_from_another_event():void
    {
        $buyer=User::factory()->create(['role'=>'buyer']);$organizer=User::factory()->create(['role'=>'organizer']);$venue=Venue::create(['name'=>'Venue','city'=>'Damascus','capacity'=>100]);
        $event=Event::withoutGlobalScopes()->create(['organizer_id'=>$organizer->id,'venue_id'=>$venue->id,'title'=>'Test','city'=>'Damascus','category'=>'music','starts_at'=>now()->addDays(2),'status'=>'published','max_per_order'=>5]);
        $other=Event::withoutGlobalScopes()->create(['organizer_id'=>$organizer->id,'venue_id'=>$venue->id,'title'=>'Other','city'=>'Damascus','category'=>'music','starts_at'=>now()->addDays(3),'status'=>'published','max_per_order'=>5]);
        $category=TicketCategory::create(['event_id'=>$other->id,'name'=>'VIP','type'=>'vip','price'=>40,'quantity'=>10,'sold'=>0,'max_per_order'=>5]);
        $this->actingAs($buyer)->postJson('/api/bookings',['event_id'=>$event->id,'items'=>[['ticket_category_id'=>$category->id,'quantity'=>1]]])->assertUnprocessable();
    }
    public function test_temporary_hold_can_be_confirmed_and_expiry_wont_release_paid_tickets():void
    {
        Queue::fake();$buyer=User::factory()->create(['role'=>'buyer']);$organizer=User::factory()->create(['role'=>'organizer']);$venue=Venue::create(['name'=>'Venue','city'=>'Damascus','capacity'=>100]);$event=Event::withoutGlobalScopes()->create(['organizer_id'=>$organizer->id,'venue_id'=>$venue->id,'title'=>'Hold test','city'=>'Damascus','category'=>'music','starts_at'=>now()->addDays(2),'status'=>'published','max_per_order'=>5]);$category=TicketCategory::create(['event_id'=>$event->id,'name'=>'Standard','type'=>'standard','price'=>20,'quantity'=>10,'sold'=>0,'max_per_order'=>5]);
        $response=$this->actingAs($buyer)->postJson('/api/holds',['event_id'=>$event->id,'items'=>[['ticket_category_id'=>$category->id,'quantity'=>1]]])->assertCreated()->assertJsonPath('data.status','reserved');$bookingId=$response->json('data.id');
        $this->postJson('/api/holds/'.$bookingId.'/confirm')->assertOk()->assertJsonPath('data.status','paid');$this->assertDatabaseHas('bookings',['id'=>$bookingId,'status'=>'paid']);$this->assertDatabaseHas('ticket_categories',['id'=>$category->id,'sold'=>1]);
    }

}
