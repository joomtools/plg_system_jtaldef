/**
 * Automatic local download external files
 *
 * @package     Joomla.Plugin
 * @subpackage  System.Jtaldef
 *
 * @author      Guido De Gobbis <support@joomtools.de>
 * @copyright   (c) 2020 JoomTools.de - All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

var Jtaldef = window.Jtaldef || {};

(function (Jtaldef, document) {
	"use strict";

	Jtaldef.clearIndex = function () {
		var item = document.querySelector('#jtaldefClearIndex'),
			cacheCounter = document.querySelector('.jtaldef-counter'),
			token = window.Joomla.getOptions('csrf.token', ''),
			processIconCss = document.createElement('style'),
			processIcon = document.createElement('span'),
			errorMessage = document.createElement('span'),
			successIcon = document.createElement('span');

		processIconCss.setAttribute("type", "text/css");
		processIconCss.appendChild(document.createTextNode(".jtaldef-spinner {\n" +
			"  display: inline-block;\n" +
			"  height: 14px;\n" +
			"  vertical-align: middle;\n" +
			"  line-height: 18px;\n" +
			"  margin-left: 4px;\n" +
			"}\n" +
			".jtaldef.icon-save::before {\n" +
			"  padding-left: 8px;\n" +
			"}\n" +
			".jtaldef-spinner > span {\n" +
			"  background-color: #fff;\n" +
			"  margin-left: 2px;\n" +
			"  height: 100%;\n" +
			"  width: 3px;\n" +
			"  display: inline-block;\n" +
			"  -webkit-animation: jtaldef-sk-stretchdelay 1.2s infinite ease-in-out;\n" +
			"  animation: jtaldef-sk-stretchdelay 1.2s infinite ease-in-out;\n" +
			"}\n" +
			".jtaldef-spinner .rect2 {\n" +
			"  -webkit-animation-delay: -1.1s;\n" +
			"  animation-delay: -1.1s;\n" +
			"}\n" +
			".jtaldef-spinner .rect3 {\n" +
			"  -webkit-animation-delay: -1.0s;\n" +
			"  animation-delay: -1.0s;\n" +
			"}\n" +
			"@-webkit-keyframes jtaldef-sk-stretchdelay {\n" +
			"  0%, 40%, 100% { -webkit-transform: scaleY(0.4) }  \n" +
			"  20% { -webkit-transform: scaleY(1.0) }\n" +
			"}\n" +
			"@keyframes jtaldef-sk-stretchdelay {\n" +
			"  0%, 40%, 100% { \n" +
			"    transform: scaleY(0.4);\n" +
			"    -webkit-transform: scaleY(0.4);\n" +
			"  }  20% { \n" +
			"    transform: scaleY(1.0);\n" +
			"    -webkit-transform: scaleY(1.0);\n" +
			"  }\n" +
			"}"));
		document.head.appendChild(processIconCss);

		processIcon.setAttribute('class', 'jtaldef-spinner');
		processIcon.setAttribute('aria-hidden', 'true');
		processIcon.innerHTML = '<span class="rect1"></span><span class="rect2"></span><span class="rect3"></span>';

		successIcon.setAttribute('class', 'jtaldef icon-save');
		successIcon.setAttribute('aria-hidden', 'true');

		errorMessage.setAttribute('class', 'error');
		errorMessage.setAttribute('aria-hidden', 'true');


		var href = item.getAttribute('data-action');

		item.addEventListener('click', function (event) {
			event.stopPropagation();
			event.preventDefault();

			//elm.parentNode.appendChild(processIcon);
			item.appendChild(processIcon);
			item.setAttribute('disabled', true);

			window.Joomla.request({
				url: href,
				headers: {
					'X-CSRF-Token': token
				},
				onError: function (xhr) {
					console.error('ERROR: ', xhr);
				},
				onSuccess: function (response) {
					var icon;

					try {
						response = JSON.parse(response);
					} catch (e) {
						response = {succsess: true};
					}

					item.removeChild(processIcon);

					if (response.success === true) {
						cacheCounter.innerHTML = '0';
						item.setAttribute('class', 'btn btn-secondary');
						item.appendChild(successIcon);
						icon = successIcon;
					}

					if (response.success === false) {
						console.error('ERROR: ', response.message);
						errorMessage.innerHTML = '<span style="margin-left:8px;color:red;">' + response.message + '</span>';
						item.parentNode.appendChild(errorMessage);
						icon = errorMessage;
					}

					if (icon !== errorMessage) {
						setTimeout(function () {
							item.removeChild(icon);
						}, 4000);
					}
				}
			});
		});

	};
}(Jtaldef, document));

function plgJtaldefReady(fn) {
	if (document.readyState != 'loading') {
		fn();
	} else {
		document.addEventListener('DOMContentLoaded', fn);
	}
}

plgJtaldefReady(Jtaldef.clearIndex);
