(function($) {

	var
	
	_defaults = {
		type:'bars',
		colors:['#f00', '#0f0', '#00f', '#ff0', '#f0f', '#0ff'],
		animate:false,
		staggered:false,
		staggerDelay:75,
		min:null,
		max:null,
		suffix:'',
		prefix:'',
		series:null
	},
	
	getArrayMaxValue = function(arr) {
		var retVal = null, val = null;
		for (var i = 0, count = arr.length; i < count; i++) {
			val = arr[i].length > 0 ? getArrayMaxValue(arr[i]) : typeof arr[i] === 'object' ? arr[i].value : arr[i];
			retVal = retVal === null || retVal < val ? val : retVal;
		}
		return retVal;
	},
	
	getArrayMinValue = function(arr) {
		var retVal = null, val = null;
		for (var i = 0, count = arr.length; i < count; i++) {
			val = arr[i].length > 0 ? getArrayMinValue(arr[i]) : typeof arr[i] === 'object' ? arr[i].value : arr[i];
			retVal = retVal === null || retVal > val ? val : retVal;
		}
		return retVal;
	},
	
	dxPlot = (function() {
		
		var
		out = '',
		min = null,
		max = null,
		opts = null,
		staggerCount = 0,
		
		bar = {
			render:function(data, min, max) {
				
				var retVal = '';
				
				for (var i = 0, count = data.length; i < count; i++) {
					retVal += '<div class="group ' + (i % 2 ? 'even' : 'odd') + ' items' + data[i].length + '">';
					retVal += bar.bars(data[i], min, max, 0);
					retVal += '</div>';
				}
				
				return retVal;
			},
			bars:function(data, min, max, index) {
				var retVal = '';

				if (data.length > 0) {
					for (var i = 0, count = data.length; i < count; i++) {
						retVal += bar.bars(data[i], min, max, i);
					}
				} else {
					var val = false, perc, label = '', from = '', above = '';
					
					label = null !== opts.series && opts.series.length > 0 ? opts.series[index] : '';

					if (typeof data === 'number') {
						val = data;
					} else {
					
						if (typeof data.value === 'number') {
							val = data.value;
						}
						if ('label' in data) {
							label = data.label;
						}
						if ('from' in data && typeof data.from === 'number') {
							from = 'data-from="' + Math.round(data.from / max * 100) + '" ';
						}
						
					}
					
					label = null != label && label.length > 0 ? '<span class="label">' + label + '</span>' : '';
					above = typeof data.above !== 'undefined' ? '<span class="above">' + data.above + '</span>' : '';
					
					if (val !== false) {
						perc = Math.round(val / max * 100);
						retVal = '<div class="barWrap"><div class="bar" data-percent="' + perc + '" ' + from + 'style="height:' + perc + '%; background-color:' + opts.colors[index % opts.colors.length] + ';">' + above + '<span class="value">' + opts.prefix + val + opts.suffix + '</span></div>' + label + '</div>';
					}
					
				}

				return retVal;
			}
		},
		
		init = function(data, options) {
		
			options = options || {};
			opts = $.extend({}, _defaults, options);
			out = '';
			
			// Array check on the data
			if (typeof data === 'object' && data.length > 0) {
				
				min = opts.min || getArrayMinValue(data);
				max = opts.max || getArrayMaxValue(data);
				
				if (typeof opts.title === 'string') {
					out += '<h1>' + opts.title + '</h1>';
				}
				
				switch (opts.type) {
					case 'bars':
						out += bar.render(data, min, max);
						break;
				}
			
				this.addClass('dxPlot').addClass(options.type).html(out);
				
				if (opts.animate) {
					staggerCount = typeof opts.staggerCount === 'number' ? opts.staggerCount : staggerCount;
					this.find('.bar').each(function() {
						var
						$this = $(this),
						minHeight = $this.css('min-height').replace('px', '') || 0,
						maxHeight = $this.css('max-height').replace('px', '') || $this.parent().height(),
						animHeight = $this.attr('data-percent') / 100 * maxHeight,
						attrFrom = $this.attr('data-from'),
						animFrom = attrFrom ? attrFrom / 100 * maxHeight : minHeight,
						$above = $this.find('.above');
						
						if (animHeight > minHeight) {
							$this.height(animFrom);
							
							anim = function() {
								if ((/webkit/ig).test(window.navigator.userAgent)) {
									$this.css({'-webkit-transition':'height ease-out 400ms', 'height':animHeight + 'px'});
								} else if ((/firefox/ig).test(window.navigator.userAgent)) {
									$this.css({'-moz-transition':'height ease-out 400ms', 'height':animHeight + 'px'});
								} else {
									$this.animate({ height:animHeight + 'px' }, 400);
								}
							};
							if (opts.staggered) {
								setTimeout(anim, staggerCount * opts.staggerDelay);
								staggerCount++;
							} else {
								anim();
							}
						} else {
							$this.height(minHeight);
						}
					});
				}
			
			}
		};
		
		return init;
		
	}());

	$.fn.dxPlot = dxPlot;
	
}(jQuery));