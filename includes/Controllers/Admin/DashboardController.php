<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Database;
use Techbiss\Core\Request;

final class DashboardController extends BaseAdminController
{
    public function index(Request $request): void
    {
        $this->authorize('dashboard.view');

        $db = Database::instance();

        $counts = [
            'messages'    => $db->int('SELECT COUNT(*) FROM contact_messages'),
            'new_messages'=> $db->int("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"),
            'quotes'      => $db->int('SELECT COUNT(*) FROM quote_requests'),
            'new_quotes'  => $db->int("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
            'customers'   => $db->int('SELECT COUNT(*) FROM customers'),
            'subscribers' => $db->int("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'subscribed'"),
            'services'    => $db->int('SELECT COUNT(*) FROM services WHERE is_published = 1'),
            'projects'    => $db->int('SELECT COUNT(*) FROM portfolio WHERE is_published = 1'),
            'posts'       => $db->int("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'"),
            'drafts'      => $db->int("SELECT COUNT(*) FROM blog_posts WHERE status <> 'published'"),
            'media'       => $db->int('SELECT COUNT(*) FROM media'),
            'industries'  => $db->int('SELECT COUNT(*) FROM industries WHERE is_published = 1'),
            'testimonials'=> $db->int('SELECT COUNT(*) FROM testimonials WHERE is_published = 1'),
        ];

        // 12 weeks of real lead volume — no synthetic data.
        $series = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = date('Y-m-d 00:00:00', strtotime("-{$i} weeks monday"));
            $end   = date('Y-m-d 23:59:59', strtotime("-{$i} weeks sunday"));
            $series[] = [
                'label'    => date('j M', strtotime($start)),
                'messages' => $db->int('SELECT COUNT(*) FROM contact_messages WHERE created_at BETWEEN ? AND ?', [$start, $end]),
                'quotes'   => $db->int('SELECT COUNT(*) FROM quote_requests WHERE created_at BETWEEN ? AND ?', [$start, $end]),
            ];
        }

        $sourceBreakdown = $db->all(
            'SELECT source, COUNT(*) AS total FROM quote_requests GROUP BY source ORDER BY total DESC'
        );

        $this->view->render('dashboard', [
            'title'           => 'Dashboard',
            'counts'          => $counts,
            'series'          => $series,
            'sourceBreakdown' => $sourceBreakdown,
            'recentMessages'  => $db->all('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5'),
            'recentQuotes'    => $db->all('SELECT * FROM quote_requests ORDER BY created_at DESC LIMIT 5'),
            'activity'        => \Techbiss\Core\Auth::can('logs.view') ? ActivityLog::recent(8) : [],
        ]);
    }
}
