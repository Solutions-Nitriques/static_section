/**
* Static section javascript file
**/
(function ($, undefined) {
	var init = function () {
		if ($('html').hasClass('no-new')) {
			$('a.create.button').remove();
		}
	};
	
	$(init);
})(jQuery);