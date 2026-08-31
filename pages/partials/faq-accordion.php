<?php
/** @var array $faqs @var string $groupId */
$groupId = $groupId ?? 'faq';
?>
<div class="accordion" data-accordion="single">
    <?php foreach ($faqs as $i => $faq):
        $id = $groupId . '-' . (int) $faq['id']; ?>
    <div class="accordion__item" id="q-<?= (int) $faq['id'] ?>">
        <h3>
            <button class="accordion__trigger" type="button"
                    aria-expanded="false" aria-controls="panel-<?= e($id) ?>" id="trigger-<?= e($id) ?>">
                <span><?= e($faq['question']) ?></span>
                <span class="accordion__icon" aria-hidden="true"><?= icon('chevron-down') ?></span>
            </button>
        </h3>
        <div class="accordion__panel" id="panel-<?= e($id) ?>" role="region"
             aria-labelledby="trigger-<?= e($id) ?>" data-open="false">
            <div>
                <div class="accordion__body"><?= nl2br(e($faq['answer'])) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
