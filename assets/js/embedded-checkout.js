var svgInfoIcon='\n  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">\n    <path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z"></path>\n  </svg>\n';jQuery(document).ready((function(e){var t=e("form.woocommerce-checkout"),n="Something went wrong. No money has been taken from your account. Please refresh the page and try again.";t.on("checkout_place_order_superpayments",(function(o,r){var s=t.find('input[name="super_payments_checkout_session_token"]').val(),c=t.find('input[name="super_payments_call_embedded_component_submit"]').val();return!function(t){let n=!0;return e(t.$checkout_form).find(".input-text, select, input:checkbox").each((function(){e(this).is(":visible")&&(e(this).trigger("validate"),e(this).closest(".form-row").hasClass("woocommerce-invalid")&&(n=!1))})),n}(r)||!s||"true"!==c||(r.blockOnSubmit(r.$checkout_form),r.attachUnloadEventsOnSubmit(),async function(e){window.superCheckout.submit().then((o=>{var r=t.find('input[name="super_payments_call_embedded_component_submit"]');if("SUCCESS"===o.status)r.val("false"),t.submit();else if("FAILURE"===o.status){var s=o.errorMessage??n;r.val("true"),e.$checkout_form&&e.$checkout_form.unblock(),e.submit_error(`<div class="wc-block-components-notice-banner is-error" role="alert">\n          ${svgInfoIcon}\n          <div class="wc-block-components-notice-banner__content">${s}</div>\n        </div>`)}}))}(r),!1)}));var o=e("#order_review");o.on("submit",(function(t){t.stopPropagation(),t.preventDefault(),$notices_wrapper=e(".woocommerce-notices-wrapper"),$notices_wrapper.find(".super-error").remove(),window.superCheckout.submit().then((t=>{if("SUCCESS"===t.status)e(this).off("submit"),o.submit();else if("FAILURE"===t.status){var r=t.errorMessage??n;$notices_wrapper.prepend(`<ul class="woocommerce-error super-error" role="alert" tabindex="-1"><li>${r}</li></ul>`),o.unblock()}}))}))}));