<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Payments\PendingPaymentGateway;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResourceShapeTest extends TestCase
{
    private const array CHECKOUT = [
        'contact_name' => 'Иван Петров',
        'contact_email' => 'ivan@example.com',
        'contact_phone' => '+79990000000',
        'shipping_address' => 'Москва, Тверская 1',
        'comment' => 'Позвонить заранее',
        'payment_method' => 'sbp',
        'delivery_method' => 'courier',
    ];

    public function test_the_category_payload_carries_every_documented_field(): void
    {
        $category = Category::factory()->create([
            'name' => 'Пептиды',
            'description' => 'Пептидные соединения',
            'position' => 20,
        ]);
        Product::factory()->count(2)->for($category)->create();

        $payload = collect($this->getJson(route('api.categories'))->assertOk()->json('data'))
            ->firstWhere('slug', $category->slug);

        $this->assertSame([
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => 'Пептиды',
            'description' => 'Пептидные соединения',
            'position' => 20,
            'products_count' => 2,
        ], $payload);
    }

    public function test_the_product_payload_carries_every_documented_field(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create([
            'name' => 'Epitalon',
            'summary' => 'Longevity-пептид',
            'maturity' => 'Экспериментальное направление',
            'supplier' => 'Корея',
            'source_url' => 'https://example.test/epitalon',
            'price_minor' => 1_200_00,
            'currency' => 'RUB',
            'stock' => 7,
        ]);

        $this->getJson(route('api.products.show', $product))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => 'Epitalon',
                    'summary' => 'Longevity-пептид',
                    'maturity' => 'Экспериментальное направление',
                    'supplier' => 'Корея',
                    'source_url' => 'https://example.test/epitalon',
                    'status' => 'published',
                    'price_minor' => 1_200_00,
                    'currency' => 'RUB',
                    'stock' => 7,
                    'weight_grams' => null,
                    'is_available' => true,
                    'category' => [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'name' => $category->name,
                        'description' => $category->description,
                        'position' => $category->position,
                    ],
                    'images' => [],
                ],
            ]);
    }

    public function test_the_product_payload_carries_the_gallery_and_the_cover(): void
    {
        $product = Product::factory()->for(Category::factory())->create();
        $cover = ProductImage::factory()->for($product)->primary()->create(['position' => 2]);
        $second = ProductImage::factory()->for($product)->create(['position' => 1]);

        $payload = $this->getJson(route('api.products.show', $product))->assertOk()->json('data');

        $this->assertSame([
            [
                'id' => $cover->id,
                'url' => $cover->url,
                'position' => 2,
                'is_primary' => true,
            ],
            [
                'id' => $second->id,
                'url' => $second->url,
                'position' => 1,
                'is_primary' => false,
            ],
        ], $payload['images'], 'the cover has to lead the gallery');

        $this->assertArrayNotHasKey('cover', $payload, 'the product page reads the cover off the gallery');
    }

    public function test_the_catalog_row_carries_the_cover_and_no_gallery(): void
    {
        $product = Product::factory()->for(Category::factory())->create();
        ProductImage::factory()->for($product)->create(['position' => 1]);
        $cover = ProductImage::factory()->for($product)->primary()->create(['position' => 2]);

        $row = $this->getJson(route('api.products'))->assertOk()->json('data.0');

        $this->assertSame([
            'id' => $cover->id,
            'url' => $cover->url,
            'position' => 2,
            'is_primary' => true,
        ], $row['cover']);

        $this->assertArrayNotHasKey('images', $row, 'a listing row must not drag the whole gallery along');
    }

    public function test_a_catalog_row_without_photos_reports_an_empty_cover(): void
    {
        Product::factory()->for(Category::factory())->create();

        $row = $this->getJson(route('api.products'))->assertOk()->json('data.0');

        $this->assertArrayHasKey('cover', $row);
        $this->assertNull($row['cover']);
    }

    public function test_a_product_without_stock_is_reported_as_unavailable(): void
    {
        $product = Product::factory()->for(Category::factory())->outOfStock()->create();

        $this->getJson(route('api.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.stock', 0)
            ->assertJsonPath('data.is_available', false);
    }

    public function test_the_cart_payload_carries_every_documented_field(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['price_minor' => 500_00, 'stock' => 9]);

        $this->actingAs($customer);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 3]);

        $cart = Cart::query()->where('customer_id', $customer->id)->sole();
        $item = CartItem::query()->where('cart_id', $cart->id)->sole();

        $response = $this->getJson(route('api.cart.show'))->assertOk();

        $this->assertSame([
            'id' => $cart->id,
            'currency' => config('shop.currency'),
            'total_minor' => 1_500_00,
            'total_quantity' => 3,
        ], collect($response->json('data'))->except('items')->all());

        $this->assertSame([
            'id' => $item->id,
            'quantity' => 3,
            'unit_price_minor' => 500_00,
            'total_minor' => 1_500_00,
        ], collect($response->json('data.items.0'))->except('product')->all());

        $this->assertSame($product->id, $response->json('data.items.0.product.id'));
    }

    public function test_the_cart_line_carries_the_cover_of_its_product(): void
    {
        $customer = Customer::factory()->create();
        $withPhoto = $this->makeProduct(['stock' => 5]);
        $withoutPhoto = $this->makeProduct(['stock' => 5]);
        ProductImage::factory()->for($withPhoto)->create(['position' => 1]);
        $cover = ProductImage::factory()->for($withPhoto)->primary()->create(['position' => 2]);

        $this->actingAs($customer);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $withPhoto->id, 'quantity' => 1]);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $withoutPhoto->id, 'quantity' => 1]);

        $lines = collect($this->getJson(route('api.cart.show'))->assertOk()->json('data.items'))
            ->keyBy('product.id');

        $this->assertSame($cover->url, $lines[$withPhoto->id]['product']['cover']['url']);
        $this->assertNull($lines[$withoutPhoto->id]['product']['cover']);
    }

    public function test_an_order_line_carries_the_cover_of_the_product_it_was_made_from(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);
        $cover = ProductImage::factory()->for($product)->primary()->create(['position' => 1]);

        $this->actingAs($customer);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $number = $this->postJson(route('api.checkout'), self::CHECKOUT)->assertCreated()->json('data.number');

        $this->getJson(route('api.orders.show', $number))
            ->assertOk()
            ->assertJsonPath('data.items.0.cover_url', $cover->url);
    }

    public function test_an_order_line_reports_no_cover_once_its_product_is_gone(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);
        ProductImage::factory()->for($product)->primary()->create(['position' => 1]);

        $this->actingAs($customer);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $number = $this->postJson(route('api.checkout'), self::CHECKOUT)->assertCreated()->json('data.number');

        $product->delete();

        $this->getJson(route('api.orders.show', $number))
            ->assertOk()
            ->assertJsonPath('data.items.0.product_id', null)
            ->assertJsonPath('data.items.0.cover_url', null)
            ->assertJsonPath('data.items.0.product_name', $product->name);
    }

    public function test_the_covers_of_an_order_do_not_cost_a_query_per_line(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer);

        foreach (range(1, 4) as $ignored) {
            $product = $this->makeProduct(['stock' => 5]);
            ProductImage::factory()->for($product)->primary()->create(['position' => 1]);
            $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
        }

        $number = $this->postJson(route('api.checkout'), self::CHECKOUT)->assertCreated()->json('data.number');

        DB::enableQueryLog();

        $this->getJson(route('api.orders.show', $number))->assertOk()->assertJsonCount(4, 'data.items');

        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));

        DB::disableQueryLog();
    }

    public function test_the_customer_payload_carries_every_documented_field(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'phone' => '+79990000000',
        ]);

        $this->actingAs($customer);

        $this->getJson(route('api.me'))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $customer->id,
                    'name' => 'Иван Петров',
                    'email' => 'ivan@example.com',
                    'phone' => '+79990000000',
                ],
            ]);
    }

    public function test_the_order_payload_carries_every_documented_field(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['name' => 'Epitalon', 'price_minor' => 900_00, 'stock' => 5]);

        $this->actingAs($customer);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2]);

        $number = $this->postJson(route('api.checkout'), self::CHECKOUT)->assertCreated()->json('data.number');
        $order = Order::query()->where('number', $number)->sole();
        $line = $order->items()->sole();

        $this->getJson(route('api.orders.show', $number))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'number' => $number,
                    'status' => 'awaiting_payment',
                    'payment_status' => 'pending',
                    'payment_method' => 'sbp',
                    'delivery_method' => 'courier',
                    'delivery_cost_minor' => 0,
                    'delivery_days_min' => null,
                    'delivery_days_max' => null,
                    'cdek_city_name' => null,
                    'cdek_tariff_name' => null,
                    'cdek_point_code' => null,
                    'cdek_point_address' => null,
                    'currency' => config('shop.currency'),
                    'total_minor' => 1_800_00,
                    'contact_name' => 'Иван Петров',
                    'contact_email' => 'ivan@example.com',
                    'contact_phone' => '+79990000000',
                    'shipping_address' => 'Москва, Тверская 1',
                    'comment' => 'Позвонить заранее',
                    'paid_at' => null,
                    'created_at' => $order->created_at->toJSON(),
                    'items' => [
                        [
                            'id' => $line->id,
                            'product_id' => $product->id,
                            'product_name' => 'Epitalon',
                            'product_slug' => $product->slug,
                            'cover_url' => null,
                            'unit_price_minor' => 900_00,
                            'quantity' => 2,
                            'total_minor' => 1_800_00,
                        ],
                    ],
                ],
            ]);
    }

    public function test_the_order_listing_counts_lines_without_embedding_them(): void
    {
        $customer = Customer::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($customer);

        Product::factory()->count(2)->for($category)->create(['stock' => 5])
            ->each(fn (Product $product) => $this->postJson(route('api.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ]));

        $this->postJson(route('api.checkout'), self::CHECKOUT)->assertCreated();

        $payload = $this->getJson(route('api.orders.index'))->assertOk()->json('data.0');

        $this->assertSame(2, $payload['items_count']);
        $this->assertArrayNotHasKey('items', $payload);
    }

    public function test_the_payment_intent_payload_carries_every_documented_field(): void
    {
        $product = $this->makeProduct(['stock' => 5]);

        $this->actingAs(Customer::factory()->create());
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $number = $this->postJson(route('api.checkout'), self::CHECKOUT)->json('data.number');

        $this->postJson(route('api.orders.pay', $number))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'status' => 'pending',
                    'provider' => PendingPaymentGateway::PROVIDER,
                    'message' => 'Платёжный провайдер ещё не подключён. Заказ зарезервирован и ожидает оплаты.',
                    'confirmation_url' => null,
                    'external_id' => null,
                ],
            ]);
    }

    public function test_checkout_normalises_the_contact_details(): void
    {
        $product = $this->makeProduct(['stock' => 5]);

        $this->actingAs(Customer::factory()->create());
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);

        $this->postJson(route('api.checkout'), [
            ...self::CHECKOUT,
            'contact_name' => '  Иван Петров  ',
            'contact_email' => '  IVAN@Example.COM ',
            'contact_phone' => ' +79990000000 ',
            'shipping_address' => '  Москва, Тверская 1  ',
        ])
            ->assertCreated()
            ->assertJsonPath('data.contact_name', 'Иван Петров')
            ->assertJsonPath('data.contact_email', 'ivan@example.com')
            ->assertJsonPath('data.contact_phone', '+79990000000')
            ->assertJsonPath('data.shipping_address', 'Москва, Тверская 1');
    }

    public function test_registration_normalises_the_name_and_email(): void
    {
        $this->postJson(route('api.register'), [
            'name' => '  Иван Петров  ',
            'email' => '  IVAN@Example.COM ',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Иван Петров')
            ->assertJsonPath('data.email', 'ivan@example.com');
    }
}
