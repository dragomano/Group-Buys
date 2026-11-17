<?php

function template_display_above() {}

function template_display_below()
{
	global $context, $txt, $user_info, $scripturl;

	$currencies = $context['group_buys_currencies'] ?? ['RUB', 'USD'];
	$currency   = empty($context['group_buy']['currency']) ? $currencies[0] : $context['group_buy']['currency'];

	// A currency removed in the settings may still be used by an existing group buy
	if (! in_array($currency, $currencies, true)) {
		array_unshift($currencies, $currency);
	}

	echo '
	<div class="modal" id="group_buy" popover="auto">
		<div class="modal-content">
			<button type="button" class="modal_close" popovertarget="group_buy" popovertargetaction="hide" aria-label="✕">✕</button>
			<h3>' . (empty($context['group_buy']['id']) ? $txt['gb_group_buy_creating'] : $txt['gb_group_buy_editing']) . '</h3>
			<form name="group_buy_form" method="post" action="javascript:void(null);">
				<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />
				<input type="hidden" name="user" value="' . $user_info['id'] . '" />
				<div class="price_row">
					<input type="number" name="price" min="0" step="0.01" ' . (! empty($context['group_buy']['price']) ? 'value="' . $context['group_buy']['price'] . '" ' : '') . 'placeholder="' . $txt['gb_enter_price'] . '" required />
					<select name="currency">';

	foreach ($currencies as $currencyCode) {
		echo '
						<option', $currencyCode === $currency ? ' selected="selected"' : '', '>', $currencyCode, '</option>';
	}

	echo '
					</select>
				</div>
				<div>
					<textarea name="requisites" placeholder="' . $txt['gb_enter_requisites'] . '" required>' . (! empty($context['group_buy']['requisites']) ? $context['group_buy']['requisites'] : '') . '</textarea>
				</div>
				<div class="centertext">
					<button type="submit" name="submit" class="button">' . $txt['post'] . '</button>
				</div>
			</form>
		</div>
	</div>
	<script>
		jQuery(document).ready(function($) {
			$("form[name=group_buy_form]").on("submit", function() {
				msg = $(this).serialize();
				$.ajax({
					type: "POST",
					url: "' . $scripturl . '?action=group_buys;sa=' . (empty($context['group_buy']['id']) ? 'add' : 'edit') . '",
					data: msg,
					success: function() {
						window.location = smf_prepareScriptUrl(smf_scripturl) + \'topic=' . $context['current_topic'] . '.0\';
					},
					error: function(xhr) {
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
			<span>', $context['page_index'], '</span>
		</div>';

		foreach ($context['items'] as $item) {
			// In SMF 2.1 all blocks just need the windowbg class: as siblings they
			// automatically alternate via .windowbg:nth-of-type(even/odd).
			echo '
		<div class="windowbg">
			<div class="counter">', $item['counter'], '</div>
			<div class="topic_details">
				<h5><strong><a href="', $scripturl, '?topic=', $item['topic'], '.0">', $item['title'], '</a></strong></h5>
				<span class="smalltext">', $item['date'], '</span>
			</div>
			<div class="list_posts">', $item['text'], '</div>';

			if ($context['gb_can_manage']) {
				echo '
			<div class="list_posts">', $item['requisites'], '</div>';

				if (! empty($context['group_buys_members'][$item['topic']])) {
					$memberLinks = [];
					foreach ($context['group_buys_members'][$item['topic']] as $memberID => $memberName) {
						$memberLinks[] = '
				<span class="gb_member"><a href="' . $scripturl . '?action=profile;u=' . $memberID . '">' . $memberName . '</a><a class="gb_member_remove" href="' . $scripturl . '?action=profile;area=group_buys;u=' . $context['member']['id'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';del_member=' . $memberID . ';del_topic=' . $item['topic'] . '" title="' . $txt['remove'] . '">✕</a></span>';
					}

					echo '
			<div class="list_posts gb_members">', implode('', $memberLinks), '</div>';
				}
			}

			if ($context['gb_can_manage'])
				echo '
			<div class="modal" id="modal_', $item['topic'], '" popover="auto">
				<div class="modal-content">
					<button type="button" class="modal_close" popovertarget="modal_', $item['topic'], '" popovertargetaction="hide" aria-label="✕">✕</button>
					<h3>' . $txt['gb_group_buy_adding_members'] . '</h3>
					<form name="group_buy_form_', $item['topic'], '" method="post" action="javascript:void(null);">
						<input type="hidden" name="topic" value="', $item['topic'], '" />
						<input type="text" name="members" id="members_control_', $item['topic'], '" placeholder="' . $txt['gb_enter_members'] . '" required />
						<div class="centertext">
							<button type="submit" name="submit" class="button">' . $txt['post'] . '</button>
						</div>
					</form>
				</div>
			</div>
			<div class="floatright">
				<ul class="reset smalltext quickbuttons">
					<li class="approve_button">
						<a href="#modal_', $item['topic'], '" data-popovertarget="modal_', $item['topic'], '"><span>', $txt['gb_add_member'], '</span></a>
					</li>
					<li class="remove_button">
						<a href="', $scripturl, '?action=profile;area=group_buys;u=', $context['member']['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], ';del_item=', $item['topic'], '"><span>', $txt['remove'], '</span></a>
					</li>
				</ul>
			</div>';

			echo '
		</div>';
		}

		echo '
		<div class="pagesection" style="margin-bottom: 0;">
			<span>', $context['page_index'], '</span>
		</div>';
	} else {
		echo '
		<div class="information">', $txt['gb_no_items'], '</div>';
	}

	if ($context['gb_can_manage']) {
		$context['insert_after_template'] .= '
		<script>
			// Username autocomplete (SMF AutoSuggest) in each add member window
			var oGroupBuySuggests = [];
			document.querySelectorAll(".modal[id^=modal_] input[name=members]").forEach(function(input) {
				window["oMemberSuggest_" + input.id] = new smc_AutoSuggest({
					sSelf: "oMemberSuggest_" + input.id,
					sSessionId: smf_session_id,
					sSessionVar: smf_session_var,
					sSuggestId: "members_" + input.id,
					sControlId: input.id,
					sSearchType: "member",
					sTextDeleteItem: ' . JavaScriptEscape($txt['autosuggest_delete_item']) . ',
					bItemList: false
				});
				oGroupBuySuggests.push(window["oMemberSuggest_" + input.id]);
			});

			// The dropdown for autocomplete is appended by smc_AutoSuggest to <body>, so
			// the popover\'s top layer would hide it: move the dropdown inside its window
			// and position it right under the input field on every show
			addLoadEvent(function() {
				oGroupBuySuggests.forEach(function(oSuggest) {
					var modal = oSuggest.oTextHandle.closest(".modal");
					if (!modal)
						return;

					modal.appendChild(oSuggest.oSuggestDivHandle);

					var origShow = oSuggest.autoSuggestShow;
					oSuggest.autoSuggestShow = function() {
						origShow.call(this);

						var inputRect = this.oTextHandle.getBoundingClientRect();
						var modalRect = modal.getBoundingClientRect();
						this.oSuggestDivHandle.style.left = Math.max(0, inputRect.left - modalRect.left) + "px";
						this.oSuggestDivHandle.style.top = (inputRect.bottom - modalRect.top) + "px";
						this.oSuggestDivHandle.style.width = inputRect.width + "px";
					};
				});
			});

			jQuery(document).ready(function($) {
				$("form[name^=group_buy_form]").on("submit", function(){
					msg = $(this).serialize();
					$.ajax({
						type: "POST",
						url: "' . $scripturl . '?action=profile;area=group_buys;u=' . $context['member']['id'] . '",
						data: msg,
						success: function() {
							window.location = smf_prepareScriptUrl(smf_scripturl) + \'action=profile;area=group_buys;u=' . $context['member']['id'] . '\';
						},
						error: function(xhr) {
							alert("' . JavaScriptEscape($txt['gb_is_error']) . '" + xhr.responseCode);
						}
					});
				});
			});
		</script>';
	}
}
