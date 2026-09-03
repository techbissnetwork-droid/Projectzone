<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ContactController extends Controller
{
    public function show(Request $request): Response
    {
        $this->seo->title('Contact TECHBISS');
        $this->seo->description('Talk to an architect about a platform, a marketplace product or a migration. We reply to every qualified enquiry within one business day.');
        $this->seo->canonical('/contact');
        $this->seo->amp('/amp/contact');
        $this->seo->breadcrumbs(['Home' => '/', 'Contact' => '/contact']);
        $this->seo->addSchema([
            '@type' => 'ContactPage',
            'name' => 'Contact TECHBISS',
            'url' => $this->seo->absolute('/contact'),
        ]);

        return $this->render('pages.contact', [
            'request' => $request,
            'topic' => $request->str('topic', 'new-project'),
            'prefill' => [
                'service' => $request->str('service'),
                'industry' => $request->str('industry'),
                'role' => $request->str('role'),
                'product' => $request->str('product'),
            ],
        ]);
    }

    public function submit(Request $request): Response
    {
        // Bot traps: a honeypot field plus a minimum render-to-submit interval.
        // Both are silent — a bot gets the same response a human does.
        $trapped = $request->str('company_website') !== ''
            || (time() - $request->int('t', 0)) < 3;

        $validator = Validator::make($request->body, [
            'name' => 'required|max:160',
            'email' => 'required|email|max:190',
            'company' => 'max:160',
            'phone' => 'phone|max:40',
            'topic' => 'required|max:60',
            'budget' => 'max:40',
            'timeline' => 'max:60',
            'message' => 'required|min:20|max:5000',
            'consent' => 'accepted',
        ], [
            'consent' => 'privacy notice',
            'message' => 'Project details',
        ]);

        if ($validator->fails()) {
            $this->withInput($request, $validator->errors());
            $this->session->flash('error', 'Please correct the highlighted fields and try again.');
            return $this->redirect('/contact#contact-form');
        }

        $data = $validator->validated();
        $reference = 'LD-' . strtoupper(bin2hex(random_bytes(4)));

        if (!$trapped) {
            $this->db->insert('leads', [
                'reference' => $reference,
                'name' => $data['name'],
                'email' => strtolower((string) $data['email']),
                'company' => $data['company'] ?? null,
                'phone' => $data['phone'] ?? null,
                'topic' => $data['topic'],
                'budget' => $data['budget'] ?? null,
                'timeline' => $data['timeline'] ?? null,
                'message' => $data['message'],
                'source' => 'contact-form',
                'status' => 'new',
                'owner_id' => null,
                'value' => null,
                'created_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ]);

            $topics = $this->config->get('site.contact_topics', []);
            $this->app->make('mailer')->send(
                (string) $this->config->get('mail.sales_inbox', 'sales@techbiss.com'),
                '[' . $reference . '] New enquiry — ' . ($topics[$data['topic']] ?? $data['topic']),
                $this->view->renderRaw('emails.lead', ['lead' => $data, 'reference' => $reference]),
                ['reply_to' => (string) $data['email']]
            );
        }

        $this->session->start();
        $this->session->flash('reference', $reference);
        return $this->redirect('/contact/thank-you');
    }

    public function thanks(Request $request): Response
    {
        $this->seo->title('Thank you');
        $this->seo->description('Your enquiry has reached the TECHBISS team.');
        $this->seo->canonical('/contact/thank-you');
        $this->seo->noindex();

        $this->session->start();

        return $this->render('pages.contact-thanks', [
            'request' => $request,
            'reference' => (string) $this->session->pullFlash('reference', ''),
        ])->cachePrivate();
    }

    public function newsletter(Request $request): Response
    {
        $validator = Validator::make($request->body, ['email' => 'required|email|max:190']);
        $this->session->start();

        if ($validator->fails()) {
            $this->session->flash('error', 'Enter a valid email address to subscribe.');
            return $this->back($request, '/');
        }

        $email = strtolower((string) $validator->validated()['email']);
        $exists = (int) $this->db->value('SELECT COUNT(*) FROM subscribers WHERE email = ?', [$email], 0);

        if ($exists === 0) {
            $this->db->insert('subscribers', [
                'email' => $email,
                'source' => 'footer',
                'status' => 'subscribed',
                'created_at' => gmdate('c'),
            ]);
        }

        // The same message either way, so the form cannot be used to test
        // whether an address is already on the list.
        $this->session->flash('status', 'You are subscribed. Field notes arrive monthly — unsubscribe any time.');
        return $this->back($request, '/');
    }
}
