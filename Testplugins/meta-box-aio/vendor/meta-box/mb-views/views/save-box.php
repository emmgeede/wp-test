<?php
$post = get_post();
$revisions = wp_get_post_revisions( $post );
?>
<div class="submitbox" id="mbv-submitpost">
	<?php if ( $revisions ) : ?>
		<div class="misc-pub-section misc-pub-revisions">
			<?php
			/* Translators: Post revisions heading. %s: The number of available revisions. */
			printf( esc_html__( 'Revisions: %s', 'mb-views' ), '<b>' . count( $revisions ) . '</b>' );
			?>
			<a href="<?php echo esc_url( wp_get_post_revisions_url( $post ) ); ?>">
				<?php esc_html_e( 'Browse', 'mb-views' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<div id="major-publishing-actions">
		<?php if ( current_user_can( 'delete_post', $post->ID ) ) : ?>
			<div id="delete-action">
				<a class="submitdelete deletion" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>">
					<?php esc_html_e( 'Move to Trash', 'mb-views' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<div id="publishing-action">
			<button type="submit" id="mbv-publish" class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'mb-views' ); ?></button>
		</div>
		<div class="clear"></div>
	</div>
</div>