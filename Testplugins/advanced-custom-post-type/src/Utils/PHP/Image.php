<?php

namespace ACPT\Utils\PHP;

use ACPT\Utils\Wordpress\WPAttachment;

class Image
{
	/**
	 * @param WPAttachment $attachment
	 * @param $width
	 * @param $height
	 *
	 * @return string
	 */
	public static function resize(WPAttachment $attachment, $width, $height)
	{
		try {
			$image = wp_get_image_editor( $attachment->getPath() );

            if ($image instanceof \WP_Error) {
                return $attachment->getSrc();
            }

			$image->resize( $width, $height, true );
			$image->save();

			$pathInfo = pathinfo($attachment->getSrc());

			return $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '-'.$width.'x'.$height.'.' . $pathInfo['extension'];
		} catch (\Exception $exception){
			return $attachment->getSrc();
		}
	}
}