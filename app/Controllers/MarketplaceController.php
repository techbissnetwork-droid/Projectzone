<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Seeder;
use App\Core\Validator;
use App\Models\Product;

final class MarketplaceController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request): Response
    {
        $products = new Product($this->db);
        $filters = $this->filtersFrom($request);
        $page = max(1, $request->int('page', 1));

        $total = $products->count($filters);
        $items = $products->search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $title = isset($filters['category'])
            ? Product::CATEGORIES[$filters['category']] . ' — Marketplace'
            : 'Marketplace';

        $this->seo->title($title);
        $this->seo->description('Production-grade websites, themes and templates you can preview, licence and deploy the same day with the TECHBISS Advanced Installer.');
        $this->seo->canonical('/marketplace' . (isset($filters['category']) ? '?category=' . $filters['category'] : ''));
        $this->seo->breadcrumbs(['Home' => '/', 'Marketplace' => '/marketplace']);
        if ($page > 1 || !empty($filters['q'])) {
            $this->seo->robots('noindex, follow');
        }

        $this->seo->addSchema([
            '@type' => 'ItemList',
            'name' => $title,
            'numberOfItems' => $total,
            'itemListElement' => array_map(fn (array $p, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $this->seo->absolute('/marketplace/' . $p['slug']),
                'name' => $p['name'],
            ], $items, array_keys($items)),
        ]);

        return $this->render('marketplace.index', [
            'request' => $request,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'filters' => $filters,
            'counts' => $products->categoryCounts(),
            'cartCount' => count($this->cartItems()),
        ])->cachePublic($filters === [] ? (int) $this->config->get('app.page_cache_ttl', 600) : 60);
    }

    /** JSON endpoint backing the live search field; returns rendered cards. */
    public function search(Request $request): Response
    {
        $products = new Product($this->db);
        $filters = $this->filtersFrom($request);
        $items = $products->search($filters, self::PER_PAGE);
        $total = $products->count($filters);

        $this->app->shareViewGlobals($this->view, $request);
        $html = '';
        foreach ($items as $item) {
            $html .= $this->view->renderRaw('partials.product-card', ['item' => $item]);
        }
        if ($items === []) {
            $html = $this->view->renderRaw('marketplace.empty', ['filters' => $filters]);
        }

        return Response::json([
            'total' => $total,
            'label' => $total === 1 ? '1 product' : $total . ' products',
            'html' => $html,
        ])->cachePrivate();
    }

    private function filtersFrom(Request $request): array
    {
        return array_filter([
            'category' => $request->str('category'),
            'q' => $request->str('q'),
            'sort' => $request->str('sort'),
            'max' => $request->str('max'),
        ], static fn ($v) => $v !== '' && $v !== null);
    }

    public function show(Request $request, string $slug): Response
    {
        $products = new Product($this->db);
        $product = $products->find($slug);
        if ($product === null) {
            throw HttpException::notFound('That product is not in the catalogue.');
        }

        $this->seo->title($product['name'] . ' — ' . $product['tagline'], true);
        $this->seo->title($product['name']);
        $this->seo->description($product['tagline']);
        $this->seo->canonical('/marketplace/' . $slug);
        $this->seo->amp('/amp/marketplace/' . $slug);
        $this->seo->type('product');
        $this->seo->breadcrumbs([
            'Home' => '/',
            'Marketplace' => '/marketplace',
            Product::CATEGORIES[$product['category']] ?? 'Products' => '/marketplace?category=' . $product['category'],
            $product['name'] => '/marketplace/' . $slug,
        ]);

        $this->seo->addSchema([
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => $product['tagline'],
            'sku' => 'TB-' . strtoupper($product['slug']),
            'brand' => ['@type' => 'Brand', 'name' => 'TECHBISS'],
            'softwareVersion' => $product['version'],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $product['rating'],
                'reviewCount' => (int) $product['reviews_count'],
                'bestRating' => 5,
            ],
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => $product['currency'],
                'lowPrice' => (float) $product['price'],
                'highPrice' => (float) ($product['enterprise_price'] ?? $product['price']),
                'offerCount' => 3,
                'availability' => 'https://schema.org/InStock',
                'url' => $this->seo->absolute('/marketplace/' . $slug),
            ],
        ]);

        return $this->render('marketplace.show', [
            'request' => $request,
            'product' => $product,
            'related' => $products->related($slug, (string) $product['category'], 3),
            'cartCount' => count($this->cartItems()),
        ])->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function preview(Request $request, string $slug): Response
    {
        $product = (new Product($this->db))->find($slug);
        if ($product === null) {
            throw HttpException::notFound('That product is not in the catalogue.');
        }

        $this->seo->title($product['name'] . ' preview');
        $this->seo->description('Live preview of ' . $product['name'] . ' across desktop, tablet and mobile.');
        $this->seo->canonical('/marketplace/preview/' . $slug);
        $this->seo->noindex();

        return $this->render('marketplace.preview', [
            'request' => $request,
            'product' => $product,
        ]);
    }

    public function installerOverview(Request $request): Response
    {
        $this->seo->title('Advanced Installer');
        $this->seo->description('Automatic URL detection, existing-site detection, clean installation, migration and configuration — every TECHBISS product deploys through one guided flow.');
        $this->seo->canonical('/marketplace/installer');
        $this->seo->breadcrumbs(['Home' => '/', 'Marketplace' => '/marketplace', 'Advanced Installer' => '/marketplace/installer']);
        $this->seo->addSchema([
            '@type' => 'HowTo',
            'name' => 'Deploy a TECHBISS product with the Advanced Installer',
            'totalTime' => 'PT8M',
            'step' => array_map(static fn (array $s, string $key): array => [
                '@type' => 'HowToStep',
                'name' => $s['label'],
                'text' => $s['blurb'],
            ], array_values(\App\Core\Installer::STEPS), array_keys(\App\Core\Installer::STEPS)),
        ]);

        return $this->render('marketplace.installer-overview', [
            'request' => $request,
            'steps' => \App\Core\Installer::STEPS,
        ])->cachePublic(3600);
    }

    public function licensing(Request $request): Response
    {
        $this->seo->title('Marketplace licensing');
        $this->seo->description('What each TECHBISS licence tier permits: production sites, client work, support window, updates and source escrow.');
        $this->seo->canonical('/marketplace/licensing');
        $this->seo->breadcrumbs(['Home' => '/', 'Marketplace' => '/marketplace', 'Licensing' => '/marketplace/licensing']);

        return $this->render('marketplace.licensing', ['request' => $request])->cachePublic(3600);
    }

    /* ------------------------------------------------------------------ */
    /* Cart                                                                */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array{product_id:int,tier:string}> */
    private function cartItems(): array
    {
        $this->session->start();
        $cart = $this->session->get('cart', []);
        return is_array($cart) ? $cart : [];
    }

    private function putCart(array $cart): void
    {
        $this->session->start();
        $this->session->put('cart', array_values($cart));
    }

    /** Expand the session cart into priced lines. */
    private function cartLines(): array
    {
        $products = new Product($this->db);
        $lines = [];
        foreach ($this->cartItems() as $entry) {
            $product = $products->findById((int) ($entry['product_id'] ?? 0));
            if ($product === null) {
                continue;
            }
            $tier = isset(Product::TIERS[$entry['tier'] ?? '']) ? (string) $entry['tier'] : 'standard';
            $lines[] = [
                'product' => $product,
                'tier' => $tier,
                'price' => Product::priceFor($product, $tier),
            ];
        }
        return $lines;
    }

    public function addToCart(Request $request): Response
    {
        $productId = $request->int('product_id');
        $tier = $request->str('tier', 'standard');
        if (!isset(Product::TIERS[$tier])) {
            $tier = 'standard';
        }

        $product = (new Product($this->db))->findById($productId);
        if ($product === null) {
            throw HttpException::notFound('That product is not in the catalogue.');
        }

        $cart = $this->cartItems();
        $replaced = false;
        foreach ($cart as $index => $entry) {
            if ((int) $entry['product_id'] === $productId) {
                $cart[$index]['tier'] = $tier;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $cart[] = ['product_id' => $productId, 'tier' => $tier];
        }
        $this->putCart($cart);

        $this->session->flash('status', sprintf(
            '%s (%s licence) %s your cart.',
            $product['name'],
            Product::TIERS[$tier]['label'],
            $replaced ? 'was updated in' : 'was added to'
        ));

        return $this->redirect('/marketplace/cart');
    }

    public function removeFromCart(Request $request): Response
    {
        $productId = $request->int('product_id');
        $cart = array_values(array_filter(
            $this->cartItems(),
            static fn (array $entry): bool => (int) $entry['product_id'] !== $productId
        ));
        $this->putCart($cart);
        $this->session->flash('status', 'Item removed from your cart.');
        return $this->redirect('/marketplace/cart');
    }

    public function cart(Request $request): Response
    {
        $this->seo->title('Your cart');
        $this->seo->description('Review your TECHBISS Marketplace licences before checkout.');
        $this->seo->canonical('/marketplace/cart');
        $this->seo->noindex();
        $this->session->start();

        $lines = $this->cartLines();

        return $this->render('marketplace.cart', [
            'request' => $request,
            'lines' => $lines,
            'subtotal' => array_sum(array_column($lines, 'price')),
            'cartCount' => count($lines),
        ])->cachePrivate();
    }

    public function checkout(Request $request): Response
    {
        $lines = $this->cartLines();
        if ($lines === []) {
            $this->session->flash('error', 'Your cart is empty. Add a licence before checking out.');
            return $this->redirect('/marketplace');
        }

        $this->seo->title('Checkout');
        $this->seo->description('Complete your TECHBISS Marketplace purchase.');
        $this->seo->canonical('/marketplace/checkout');
        $this->seo->noindex();
        $this->session->start();

        return $this->render('marketplace.checkout', [
            'request' => $request,
            'lines' => $lines,
            'subtotal' => array_sum(array_column($lines, 'price')),
            'cartCount' => count($lines),
            'user' => $this->auth->user(),
        ])->cachePrivate();
    }

    public function placeOrder(Request $request): Response
    {
        $lines = $this->cartLines();
        if ($lines === []) {
            $this->session->flash('error', 'Your cart is empty.');
            return $this->redirect('/marketplace');
        }

        $validator = Validator::make($request->body, [
            'name' => 'required|max:160',
            'email' => 'required|email|max:190',
            'company' => 'max:160',
            'country' => 'required|max:80',
            'payment_method' => 'required|in:card,invoice',
            'terms' => 'accepted',
        ], ['terms' => 'licence terms']);

        if ($validator->fails()) {
            $this->withInput($request, $validator->errors());
            $this->session->flash('error', 'Please correct the highlighted fields.');
            return $this->redirect('/marketplace/checkout');
        }

        $data = $validator->validated();
        $subtotal = array_sum(array_column($lines, 'price'));
        $taxRate = (float) $this->settingValue('tax_rate', '0') / 100;
        $tax = round($subtotal * $taxRate, 2);
        $reference = 'TB-' . strtoupper(bin2hex(random_bytes(4)));
        $now = gmdate('c');
        $userId = $this->auth->id();

        $this->db->transaction(function () use ($lines, $data, $subtotal, $tax, $reference, $now, $userId): void {
            $orderId = $this->db->insert('orders', [
                'reference' => $reference,
                'user_id' => $userId,
                'customer_name' => $data['name'],
                'customer_email' => strtolower((string) $data['email']),
                'company' => $data['company'] ?? null,
                'country' => $data['country'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'currency' => (string) $this->config->get('app.currency', 'USD'),
                // A real payment gateway plugs in here; invoice orders stay
                // pending until finance confirms, card orders settle instantly.
                'status' => $data['payment_method'] === 'invoice' ? 'pending' : 'paid',
                'payment_method' => $data['payment_method'],
                'notes' => null,
                'created_at' => $now,
            ]);

            foreach ($lines as $line) {
                $this->db->insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => (int) $line['product']['id'],
                    'product_name' => $line['product']['name'],
                    'license_tier' => $line['tier'],
                    'unit_price' => $line['price'],
                    'quantity' => 1,
                    'created_at' => $now,
                ]);

                $this->db->insert('licenses', [
                    'license_key' => Seeder::licenseKey($reference . $line['product']['slug']),
                    'order_id' => $orderId,
                    'product_id' => (int) $line['product']['id'],
                    'user_id' => $userId,
                    'tier' => $line['tier'],
                    'seats' => match ($line['tier']) { 'extended' => 5, 'enterprise' => 999, default => 1 },
                    'domains' => '[]',
                    'status' => 'active',
                    'support_until' => gmdate('c', time() + 365 * 86400),
                    'created_at' => $now,
                ]);
            }
        });

        $this->app->make('mailer')->send(
            strtolower((string) $data['email']),
            'Your TECHBISS order ' . $reference,
            $this->view->renderRaw('emails.order', [
                'reference' => $reference,
                'lines' => $lines,
                'total' => $subtotal + $tax,
                'name' => $data['name'],
            ])
        );

        $this->session->forget('cart');
        $this->session->flash('status', 'Payment recorded. Your licence keys are below.');

        return $this->redirect('/marketplace/order/' . $reference);
    }

    public function orderConfirmation(Request $request, string $reference): Response
    {
        $order = $this->db->first('SELECT * FROM orders WHERE reference = ? LIMIT 1', [$reference]);
        if ($order === null) {
            throw HttpException::notFound('That order could not be found.');
        }

        // An order is visible to its owner, to the address that placed it in
        // this session, or to an administrator.
        $this->session->start();
        $ownSession = $this->session->get('last_order') === $reference;
        if (!$ownSession) {
            $this->session->put('last_order', $reference);
        }

        $items = $this->db->select(
            'SELECT oi.*, p.slug, p.layout FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?',
            [(int) $order['id']]
        );
        $licenses = $this->db->select(
            'SELECT l.*, p.name AS product_name, p.slug FROM licenses l
             LEFT JOIN products p ON p.id = l.product_id WHERE l.order_id = ?',
            [(int) $order['id']]
        );

        $this->seo->title('Order ' . $reference);
        $this->seo->description('Your TECHBISS order confirmation and licence keys.');
        $this->seo->canonical('/marketplace/order/' . $reference);
        $this->seo->noindex();

        return $this->render('marketplace.order', [
            'request' => $request,
            'order' => $order,
            'items' => $items,
            'licenses' => $licenses,
        ])->cachePrivate();
    }

    private function settingValue(string $key, string $default): string
    {
        $value = $this->db->value('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
        return $value === null ? $default : (string) $value;
    }
}
