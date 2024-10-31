<?
header('Content-Type: application/javascript');
$apid = $_REQUEST['apid'];
?>
// -*- tab-width: 4; coding: utf-8; -*-
if (typeof escapeHTML != 'function') {
	function escapeHTML(str)
	{
		return String(str).replace(/&/g, '&amp;')
			.replace(/\"/g, '&quot;')
			.replace(/\'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}
}

jQuery(document).ready(function($) {
	var custom_uploader;
	var curr_image_idx;

	$('#image-uploader-button').click(function(e) {
		$('#image-elem').html('');
		$('#image-preview').html('');
		arr_images = new Array();
		e.preventDefault();
		if (custom_uploader) {
			custom_uploader.open();
			return;
		}
		custom_uploader = wp.media({
			title: 'Choose Image',
			library: {
				type: 'image'
			},
			button: {
				text: 'Choose Image'
			},
			multiple: true
		});
		custom_uploader.on('select', function() {
			var images = custom_uploader.state().get('selection');
			var arr_image = new Array();
			var image_idx = 0;
			images.each(function(file) {
				var file_json = file.toJSON();
				var aux = {
					image_key: sprintf(image_key_format, image_idx + 1),
				};
				$('#image-preview')
					.append('<div><input type="text" id="image_key[' + image_idx + ']" value="' + aux.image_key + '" title="テンプレートで使用する画像識別用のキー" placeholder="画像識別用" /><br /><img src="' + file_json.url + '" alt="' + file_json.alt + '" title="' + file_json.title + '" /></div>');
				var image_files = new Array();
				// full
				if (file_json.sizes.hasOwnProperty('full')) {
					image_files.push({
						"url": file_json.sizes.full.url,
						"width": file_json.sizes.full.width,
						"height": file_json.sizes.full.height,
						"mime-type": file_json.mime,
					});		
				}
				// large
				if (file_json.sizes.hasOwnProperty('large')) {
					image_files.push({
						"url": file_json.sizes.large.url,
						"width": file_json.sizes.large.width,
						"height": file_json.sizes.large.height,
						"mime-type": file_json.mime,
					});		
				}
				// medium
				if (file_json.sizes.hasOwnProperty('medium')) {
					image_files.push({
						"url": file_json.sizes.medium.url,
						"width": file_json.sizes.medium.width,
						"height": file_json.sizes.medium.height,
						"mime-type": file_json.mime,
					});		
				}
				// thumbnail
				if (file_json.sizes.hasOwnProperty('thumbnail')) {
					image_files.push({
						"url": file_json.sizes.thumbnail.url,
						"width": file_json.sizes.thumbnail.width,
						"height": file_json.sizes.thumbnail.height,
						"mime-type": file_json.mime,
					});		
				}
				arr_image.push({
					'<?= $apid ?>_aux': aux,
					'files': image_files,
					'alt': file_json.alt,
					'title': file_json.title,
					'description': file_json.description
				});
				image_idx++;
			});
			$('#image-preview').append('<div class="clear"></div>');
			$('#image-elem').append('<input type="hidden" id="entry-image" name="entry_image" value="' + escapeHTML(JSON.stringify(arr_image)) + '" />');
		});
		custom_uploader.open();
	});
});
