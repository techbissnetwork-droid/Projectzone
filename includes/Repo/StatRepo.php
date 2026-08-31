<?php
declare(strict_types=1);

namespace Techbiss\Repo;

/**
 * Headline numbers shown on the site. Seeded empty on purpose — a statistic is
 * only ever displayed once an administrator has entered a real one.
 */
final class StatRepo extends BaseRepo
{
    protected string $table = 'stats';
}
