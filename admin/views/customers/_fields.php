<?php
/**
 * The customer fields, shared by the new-customer form and the edit form so the
 * two cannot drift.
 *
 * @var callable $val @var array $industries
 */
?>
                    <div class="form-section">
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="cu-name">Name <span class="req">*</span></label>
                                <input class="input" id="cu-name" type="text" name="name" value="<?= e($val('name')) ?>" required maxlength="190">
                            </div>
                            <div class="field">
                                <label class="label" for="cu-business">Business name</label>
                                <input class="input" id="cu-business" type="text" name="business_name" value="<?= e($val('business_name')) ?>" maxlength="190">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="cu-email">Email <span class="req">*</span></label>
                                <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="cu-email" type="email"
                                       name="email" value="<?= e($val('email')) ?>" required maxlength="190"
                                       <?= $view->partial('partials/field-invalid', ['key' => 'email']) ?>>
                                <?= $view->partial('partials/field-error', ['key' => 'email', 'withIcon' => false]) ?>
                            </div>
                            <div class="field">
                                <label class="label" for="cu-phone">Phone</label>
                                <input class="input" id="cu-phone" type="tel" name="phone" value="<?= e($val('phone')) ?>" maxlength="32">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="cu-country">Country</label>
                                <input class="input" id="cu-country" type="text" name="country" value="<?= e($val('country')) ?>" maxlength="80">
                            </div>
                            <div class="field">
                                <label class="label" for="cu-city">City</label>
                                <input class="input" id="cu-city" type="text" name="city" value="<?= e($val('city')) ?>" maxlength="120">
                            </div>
                            <div class="field">
                                <label class="label" for="cu-website">Website</label>
                                <input class="input" id="cu-website" type="text" name="website" value="<?= e($val('website')) ?>" maxlength="255">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="cu-industry">Industry</label>
                                <select class="select" id="cu-industry" name="industry_id">
                                    <option value="">None</option>
                                    <?php foreach ($industries as $ind): ?>
                                    <option value="<?= (int) $ind['id'] ?>" <?= (int) $val('industry_id') === (int) $ind['id'] ? 'selected' : '' ?>><?= e($ind['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="cu-status">Status</label>
                                <select class="select" id="cu-status" name="status">
                                    <?php foreach (['lead' => 'Lead', 'active' => 'Active customer', 'inactive' => 'Inactive'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= $val('status') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="cu-notes">Internal notes</label>
                            <textarea class="textarea" id="cu-notes" name="notes" maxlength="5000"><?= e($val('notes')) ?></textarea>
                        </div>
                    </div>
