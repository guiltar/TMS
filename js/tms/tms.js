/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{

	XenForo.TemplateText = function($element) { this.__construct($element); };
	XenForo.TemplateText.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input;
			this.url = $input.data('textUrl');
			this.$target = $($input.data('textTarget'));
			if (!this.url || !this.$target.length)
			{
				return;
			}

			$input.bind(
			{
				keyup: $.context(this, 'fetchTextDelayed'),
				change: $.context(this, 'fetchTextDelayed')
			});
			if ($input.val().length)
			{
				this.fetchText();
			}
		},

		fetchTextDelayed: function()
		{
			if (this.delayTimer)
			{
				clearTimeout(this.delayTimer);
			}

			this.delayTimer = setTimeout($.context(this, 'fetchText'), 250);
		},

		fetchText: function()
		{
			if (!this.$input.val().length)
			{
				this.$target.text('');
				return;
			}

			if (this.xhr)
			{
				this.xhr.abort();
			}

			this.xhr = XenForo.ajax(
				this.url,
				{ template_title: this.$input.val() },
				$.context(this, 'ajaxSuccess'),
				{ error: false }
			);
		},

		ajaxSuccess: function(ajaxData)
		{
			if (ajaxData)
			{
				this.$target.text(ajaxData.template);
			}
			else
			{
				this.$target.text('');
			}
		}
	};

	XenForo.register('input.TemplateText', 'XenForo.TemplateText');

}
(jQuery, this, document);