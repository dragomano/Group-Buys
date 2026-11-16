<?php

function template_display_above() {}

function template_display_below()
{
	global $context, $txt, $user_info, $scripturl;

	$currency = empty($context['group_buy']['currency']) ? 'RUB' : $context['group_buy']['currency'];

	echo '
	<div class="modal" id="group_buy" style="display:none">
		<div class="modal-content">
			<h3>' . (empty($context['group_buy']['id']) ? $txt['gb_group_buy_creating'] : $txt['gb_group_buy_editing']) . '</h3>
			<form name="group_buy_form" method="post" action="javascript:void(null);">
				<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />
				<input type="hidden" name="user" value="' . $user_info['id'] . '" />
				<div>
					<input type="number" name="price" min="0" step="0.01" ' . (! empty($context['group_buy']['price']) ? 'value="' . $context['group_buy']['price'] . '" ' : '') . 'placeholder="' . $txt['gb_enter_price'] . '" required />
					<select name="currency">
						<option' . ($currency == 'RUB' ? ' selected="selected"' : '') . '>RUB</option>
						<option' . ($currency == 'UAH' ? ' selected="selected"' : '') . '>UAH</option>
						<option' . ($currency == 'USD' ? ' selected="selected"' : '') . '>USD</option>
						<option' . ($currency == 'EUR' ? ' selected="selected"' : '') . '>EUR</option>
					</select>
				</div>
				<div>
					<textarea name="requisites" placeholder="' . $txt['gb_enter_requisites'] . '" required>' . (! empty($context['group_buy']['requisites']) ? $context['group_buy']['requisites'] : '') . '</textarea>
				</div>
				<div class="centertext">
					<button type="submit" name="submit" class="button_submit">' . $txt['post'] . '</button>
				</div>
			</form>
		</div>
	</div>
	<script>
		jQuery(document).ready(function($) {
			$(".button_strip_' . (empty($context['group_buy']['id']) ? 'add' : 'edit') . '_group_buy").attr("rel", "modal:open");
			$("form[name=group_buy_form]").on("submit", function() {
				msg = $(this).serialize();
				$.ajax({
					type: "POST",
					url: "' . $scripturl . '?action=group_buys;sa=' . (empty($context['group_buy']['id']) ? 'add' : 'edit') . '",
					data: msg,
					success: function() {
						window.location = smf_prepareScriptUrl(smf_scripturl) + \'topic=' . $context['current_topic'] . '.0\';
					},
					error: function() {
						alert("' . JavaScriptEscape($txt['gb_is_error']) . '" + xhr.responseCode);
					}
				});
			});
		});
	</script>';
}

function template_profile()
{
	global $context, $scripturl, $txt;

	if (! empty($context['items'])) {
		echo '
		<div class="pagesection">
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';

		foreach ($context['items'] as $item) {
			echo '
		<div class="topic">
			<div class="', $item['alternate'] == 0 ? 'windowbg2' : 'windowbg', ' core_posts">
				<span class="topslice"><span></span></span>
				<div class="content">
					<div class="counter">', $item['counter'], '</div>
					<div class="topic_details">
						<h5><strong><a href="', $scripturl, '?topic=', $item['topic'], '.0">', $item['title'], '</a></strong></h5>
						<span class="smalltext">', $item['date'], '</span>
					</div>
					<div class="list_posts">', $item['text'], '</div>';

			if ($context['gb_can_manage']) {
				echo '
					<div class="list_posts">', $item['requisites'], '</div>';

				if (! empty($context['group_buys_members'][$item['topic']]))
					echo '
					<div class="list_posts">', implode(', ', $context['group_buys_members'][$item['topic']]), '</div>';
			}

			echo '
				</div>';

			if ($context['gb_can_manage'])
				echo '
				<div class="modal" id="modal_', $item['topic'], '" style="display:none">
					<div class="modal-content">
						<h3>' . $txt['gb_group_buy_adding_members'] . '</h3>
						<form name="group_buy_form_', $item['topic'], '" method="post" action="javascript:void(null);">
							<input type="hidden" name="topic" value="', $item['topic'], '" />
							<input type="text" name="members" placeholder="' . $txt['gb_enter_members'] . '" required />
							<div class="centertext">
								<button type="submit" name="submit" class="button_submit">' . $txt['post'] . '</button>
							</div>
						</form>
					</div>
				</div>';

			if ($context['gb_can_manage'])
				echo '
				<div class="floatright">
					<ul class="reset smalltext quickbuttons">
						<li class="approve_button">
							<a href="#modal_', $item['topic'], '"><span>', $txt['gb_add_member'], '</span></a>
						</li>
						<li class="remove_button">
							<a href="', $scripturl, '?action=profile;area=group_buys;u=', $context['member']['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], ';del_item=', $item['topic'], '"><span>', $txt['remove'], '</span></a>
						</li>
					</ul>
				</div>';

			echo '
				<br class="clear" />
				<span class="botslice"><span></span></span>
			</div>
		</div>';
		}

		echo '
		<div class="pagesection" style="margin-bottom: 0;">
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';
	} else {
		echo '
		<div class="information">', $txt['gb_no_items'], '</div>';
	}

	if ($context['gb_can_manage']) {
		$context['insert_after_template'] .= '
		<script>
			jQuery(document).ready(function($) {
				$(".approve_button a").attr("rel", "modal:open");
				$("form[name^=group_buy_form]").on("submit", function(){
					msg = $(this).serialize();
					$.ajax({
						type: "POST",
						url: "' . $scripturl . '?action=profile;area=group_buys;u=' . $context['member']['id'] . '",
						data: msg,
						success: function() {
							window.location = smf_prepareScriptUrl(smf_scripturl) + \'action=profile;area=group_buys;u=' . $context['member']['id'] . '\';
						},
						error: function() {
							alert("' . JavaScriptEscape($txt['gb_is_error']) . '" + xhr.responseCode);
						}
					});
				});
			});
		</script>';
	}
}
