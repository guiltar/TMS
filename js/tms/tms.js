/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
    XenForo.DiffLoader = function($trigger) {
        if(window.location.hash=='#diff')
        {
            this.OverlayLoader = new XenForo.OverlayLoader($trigger, false, {});
            this.OverlayLoader.load();
            return false;
        }
    };

	XenForo.TemplateText = function($element) { this.__construct($element); };
	XenForo.TemplateText.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input;
			this.url = $('.TemplateText').data('templateUrl');
			this.$originalTemplate = $('#templateOriginal');
			this.$finalTemplate = $('#templateFinal');

			$('.AutoComplete.TemplateText').bind(
			{
				//click: $.context(this, 'fetchTextDelayed'),
				keyup: $.context(this, 'fetchTextDelayed'),
				change: $.context(this, 'fetchTextDelayed')
			});

			$input.bind(
			{
				click: $.context(this, 'fetchText')
			});

			this.fetchText();
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
			if (!$('#templateTitle').val())
			{
				this.$originalTemplate.text('');
				this.$finalTemplate.text('');
				return;
			}

			if (this.xhr)
			{
				this.xhr.abort();
			}

			this.xhr = XenForo.ajax(
				this.url,
				{ title: $('#templateTitle').val() },
				$.context(this, 'ajaxSuccess'),
				{ error: false }
			);
		},

		ajaxSuccess: function(ajaxData)
		{
			if (ajaxData)
			{
				this.$originalTemplate.text(ajaxData.template);
				this.$finalTemplate.text(ajaxData.template_final);
			}
			else
			{
				this.$originalTemplate.text('');
				this.$finalTemplate.text('');
			}
		}
	};



	XenForo.FinalTemplateToggle = function($form)
	{
		var $templateFinal = $form.find('#templateFinal').parent().parent();

		$form.find('input[name=template_final]').click(function(e){
			$templateFinal.toggle();
		});

		$form.find('input[name=reload]').click(function(e){
			$templateFinal.hide();
		});
	};

	XenForo.register('form', 'XenForo.FinalTemplateToggle');
    XenForo.register('.OverlayTrigger[name=diff]', 'XenForo.DiffLoader');
	XenForo.register('input.TemplateText', 'XenForo.TemplateText');

}
(jQuery, this, document);