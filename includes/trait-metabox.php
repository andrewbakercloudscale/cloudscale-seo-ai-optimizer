<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Metabox {
    public function add_metabox(): void {
        foreach (['post', 'page'] as $pt) {
            add_meta_box('cs_seo_adv', 'CloudScale Meta Boxes', [$this, 'render_metabox'], $pt, 'normal', 'high');
        }
    }

    public function render_metabox(WP_Post $post): void {
        wp_nonce_field('cs_seo_save', 'cs_seo_nonce');
        $noindex = (int) get_post_meta($post->ID, self::META_NOINDEX, true);
        $title   = (string) get_post_meta($post->ID, self::META_TITLE,    true);
        $desc    = (string) get_post_meta($post->ID, self::META_DESC,     true);
        $ogimg   = (string) get_post_meta($post->ID, self::META_OGIMG,    true);
        $sum_what = (string) get_post_meta($post->ID, self::META_SUM_WHAT, true);
        $sum_why  = (string) get_post_meta($post->ID, self::META_SUM_WHY,  true);
        $sum_key  = (string) get_post_meta($post->ID, self::META_SUM_KEY,  true);
        $has_key = !empty($this->ai_opts['anthropic_key']) || !empty($this->ai_opts['gemini_key']);
        ?>
        <p style="margin:0 0 12px;padding:8px 10px;background:<?php echo $noindex ? '#fff3cd' : '#f6f7f7'; ?>;border:1px solid <?php echo $noindex ? '#ffc107' : '#ddd'; ?>;border-radius:4px;display:flex;align-items:center;gap:8px">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;margin:0">
                <input type="checkbox" name="cs_seo_noindex" value="1" <?php checked($noindex, 1); ?>>
                <span style="color:<?php echo $noindex ? '#856404' : '#3c434a'; ?>">
                    <?php echo $noindex ? '⛔ Noindex — hidden from search engines' : 'Noindex this post/page'; ?>
                </span>
            </label>
            <?php if (!$noindex): ?>
            <span style="font-size:11px;color:#888;font-weight:400">— tick to exclude from search engines</span>
            <?php endif; ?>
        </p>
        <p><strong>Custom SEO title</strong> — leave blank to auto-generate<br>
            <input class="widefat" name="cs_seo_title" value="<?php echo esc_attr($title); ?>"></p>
        <p>
            <strong>Meta description</strong> — leave blank to use excerpt / post content<br>
            <textarea class="widefat" rows="3" name="cs_seo_desc" id="cs_seo_desc_<?php echo (int) $post->ID; ?>"><?php echo esc_textarea($desc); ?></textarea>
            <span id="cs_seo_char_<?php echo (int) $post->ID; ?>" style="font-size:11px;color:#888;">
                <?php echo $desc ? esc_html( (string) mb_strlen($desc) ) . ' chars' : 'No description set'; ?>
            </span>
        </p>
        <?php if ($has_key): ?>
        <p>
            <button type="button" class="button" id="cs_seo_gen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoGenOne(<?php echo (int) $post->ID; ?>)">
                ✦ Generate with Claude
            </button>
            <span id="cs_seo_gen_status_<?php echo (int) $post->ID; ?>" style="margin-left:8px;font-size:12px;color:#888;"></span>
        </p>
        <script>
        function csSeoGenOne(postId) {
            const btn    = document.getElementById('cs_seo_gen_' + postId);
            const status = document.getElementById('cs_seo_gen_status_' + postId);
            const field  = document.getElementById('cs_seo_desc_' + postId);
            const chars  = document.getElementById('cs_seo_char_' + postId);
            btn.disabled = true;
            status.textContent = '⟳ Generating...';
            status.style.color = '#888';
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'cs_seo_ai_generate_one',
                    post_id: postId,
                    nonce: '<?php echo esc_js( wp_create_nonce('cs_seo_nonce') ); ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    field.value = data.data.description;
                    chars.textContent = data.data.chars + ' chars';
                    chars.style.color = data.data.chars >= 140 && data.data.chars <= 160 ? '#46b450' : '#dc3232';
                    status.textContent = '✓ Done — save post to keep';
                    status.style.color = '#46b450';
                } else {
                    status.textContent = '✗ ' + (data.data || 'Error');
                    status.style.color = '#dc3232';
                }
            })
            .catch(e => {
                status.textContent = '✗ ' + e.message;
                status.style.color = '#dc3232';
            })
            .finally(() => { btn.disabled = false; });
        }
        </script>
        <?php else: ?>
        <p style="color:#888;font-size:12px;"><em>Add an Anthropic API key in <a href="<?php echo esc_url( admin_url('options-general.php?page=cs-seo-optimizer#ai') ); ?>">SEO Settings → AI Meta Writer</a> to enable per-post generation.</em></p>
        <?php endif; ?>
        <?php
        $thumb_id  = get_post_thumbnail_id($post->ID);
        $thumb_src = $thumb_id ? wp_get_attachment_image_src($thumb_id, 'thumbnail') : false;
        $using_custom = !empty($ogimg);
        ?>
        <p>
            <strong>OG image URL</strong> — leave blank to use featured image<br>
            <input class="widefat" name="cs_seo_ogimg" id="cs_seo_ogimg_<?php echo (int) $post->ID; ?>" value="<?php echo esc_attr($ogimg); ?>">
            <?php if ($using_custom): ?>
            <button type="button" class="button" style="margin-top:4px" onclick="
                document.getElementById('cs_seo_ogimg_<?php echo (int) $post->ID; ?>').value = '';
                this.parentNode.querySelector('.cs-og-status').textContent = '⚠ Cleared — save post to apply';
                this.parentNode.querySelector('.cs-og-status').style.color = '#e67e00';
                this.style.display = 'none';
            ">✕ Clear (use featured image)</button>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#c3372b;margin-top:3px">⚠ Custom URL set — featured image changes will not appear until this is cleared</span>
            <?php elseif ($thumb_src): ?>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#1a7a34;margin-top:3px">✓ Using featured image</span>
            <?php else: ?>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#888;margin-top:3px">No featured image set — using site default OG image</span>
            <?php endif; ?>
        </p>

        <hr style="margin:16px 0;border:none;border-top:1px solid #ddd">
        <?php $hide_summary = (int) get_post_meta($post->ID, self::META_HIDE_SUMMARY, true); ?>
        <p style="margin:0 0 8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
            <span><strong>AI Summary Box</strong> <span style="font-size:11px;font-weight:400;color:#888">— shown at the top of the post for readers and AI search engines</span></span>
            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;">
                <input type="checkbox" name="cs_seo_hide_summary" value="1" <?php checked($hide_summary, 1); ?>>
                <span style="color:#c3372b;font-weight:600">Hide on this post</span>
            </label>
        </p>

        <p style="margin:0 0 6px">
            <label style="font-size:12px;font-weight:600;color:#555">What it is</label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_what" id="cs_seo_sum_what_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_what); ?></textarea>
        </p>
        <p style="margin:0 0 6px">
            <label style="font-size:12px;font-weight:600;color:#555">Why it matters</label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_why" id="cs_seo_sum_why_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_why); ?></textarea>
        </p>
        <p style="margin:0 0 10px">
            <label style="font-size:12px;font-weight:600;color:#555">Key takeaway</label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_key" id="cs_seo_sum_key_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_key); ?></textarea>
        </p>

        <?php if ($has_key): ?>
        <p style="margin:0">
            <button type="button" class="button" id="cs_seo_sum_gen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoSumGenOne(<?php echo (int) $post->ID; ?>)">
                ✦ Generate Summary
            </button>
            <button type="button" class="button" style="margin-left:6px" id="cs_seo_sum_regen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoSumGenOne(<?php echo (int) $post->ID; ?>, true)">
                ↺ Regenerate
            </button>
            <span id="cs_seo_sum_status_<?php echo (int) $post->ID; ?>" style="margin-left:8px;font-size:12px;color:#888;"></span>
        </p>
        <script>
        function csSeoSumGenOne(postId, force) {
            const btn    = document.getElementById('cs_seo_sum_gen_' + postId);
            const regen  = document.getElementById('cs_seo_sum_regen_' + postId);
            const status = document.getElementById('cs_seo_sum_status_' + postId);
            const fWhat  = document.getElementById('cs_seo_sum_what_' + postId);
            const fWhy   = document.getElementById('cs_seo_sum_why_' + postId);
            const fKey   = document.getElementById('cs_seo_sum_key_' + postId);
            btn.disabled = true;
            regen.disabled = true;
            status.textContent = '⟳ Generating...';
            status.style.color = '#888';
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'cs_seo_summary_generate_one',
                    post_id: postId,
                    force: force ? 1 : 0,
                    nonce: '<?php echo esc_js( wp_create_nonce('cs_seo_nonce') ); ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.data.skipped) {
                        status.textContent = '✓ Already generated — use Regenerate to overwrite';
                        status.style.color = '#888';
                    } else {
                        fWhat.value = data.data.what;
                        fWhy.value  = data.data.why;
                        fKey.value  = data.data.takeaway;
                        status.textContent = '✓ Done — save post to keep';
                        status.style.color = '#46b450';
                    }
                } else {
                    status.textContent = '✗ ' + (data.data || 'Error');
                    status.style.color = '#dc3232';
                }
            })
            .catch(e => {
                status.textContent = '✗ ' + e.message;
                status.style.color = '#dc3232';
            })
            .finally(() => { btn.disabled = false; regen.disabled = false; });
        }
        </script>
        <?php endif; ?>

        <?php
    }

    public function save_metabox(int $post_id, WP_Post $post): void {
        if (!isset($_POST['cs_seo_nonce'])) return;
        if (!wp_verify_nonce( sanitize_key( wp_unslash( $_POST['cs_seo_nonce'] ) ), 'cs_seo_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $this->set_meta($post_id, self::META_TITLE,    sanitize_text_field( wp_unslash( (string) ($_POST['cs_seo_title'] ?? '') ) ));
        $this->set_meta($post_id, self::META_DESC,     sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_desc'] ?? '') ) ));
        $this->set_meta($post_id, self::META_OGIMG,    esc_url_raw( wp_unslash( (string) ($_POST['cs_seo_ogimg'] ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_WHAT, sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_what'] ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_WHY,  sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_why']  ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_KEY,  sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_key']  ?? '') ) ));
        $hide = isset($_POST['cs_seo_hide_summary']) ? 1 : 0;
        $hide ? update_post_meta($post_id, self::META_HIDE_SUMMARY, 1) : delete_post_meta($post_id, self::META_HIDE_SUMMARY);
        $noindex = isset($_POST['cs_seo_noindex']) ? 1 : 0;
        $noindex ? update_post_meta($post_id, self::META_NOINDEX, 1) : delete_post_meta($post_id, self::META_NOINDEX);
    }

    private function set_meta(int $id, string $key, string $val): void {
        $val === '' ? delete_post_meta($id, $key) : update_post_meta($id, $key, $val);
    }

    /**
     * When the featured image (_thumbnail_id) is changed, clear our custom OG image
     * so og_image_data() falls through to the new featured image automatically.
     */
    public function on_thumbnail_updated(int $meta_id, int $post_id, string $meta_key, $meta_value): void {
        if ($meta_key !== '_thumbnail_id') return;
        // Only clear if our custom OG image field is set — if it's empty, nothing to do.
        $custom = get_post_meta($post_id, self::META_OGIMG, true);
        if ($custom) {
            delete_post_meta($post_id, self::META_OGIMG);
        }
    }

}
