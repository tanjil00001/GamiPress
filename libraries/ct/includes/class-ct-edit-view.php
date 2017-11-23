<?php
/**
 * Edit View class
 *
 * @since 1.0.0
 */
// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CT_Edit_View' ) ) :

    class CT_Edit_View extends CT_View {

        protected $object_id = 0;
        protected $object = null;
        protected $editing = false;
        protected $message = false;
        protected $columns = 2;

        public function __construct($name, $args) {

            parent::__construct($name, $args);

            $this->columns = isset( $args['columns'] ) ? $args['columns'] : 2;

        }

        public function init() {

            global $ct_registered_tables, $ct_table;

            if( ! isset( $ct_registered_tables[$this->name] ) ) {
                wp_die( __( 'Invalid item type.' ) );
            }

            // Setup global $ct_table
            $ct_table = $ct_registered_tables[$this->name];

            // If not CT object, die
            if ( ! $ct_table )
                wp_die( __( 'Invalid item type.' ) );

            // If not CT object allow ui, die
            if ( ! $ct_table->show_ui ) {
                wp_die( __( 'Sorry, you are not allowed to edit items of this type.' ) );
            }

            if( isset( $_POST['ct-save'] ) ) {
                // Saving
                $this->save();
            }

            $primary_key = $ct_table->db->primary_key;

            if( isset( $_GET[$primary_key] ) ) {
                // Editing object
                $this->object_id = (int) $_GET[$primary_key];
                $this->object = $ct_table->db->get( $this->object_id );
                $this->editing = true;

                // If not id, return to list
                if ( empty( $this->object_id ) ) {
                    wp_redirect( ct_get_list_link( $ct_table->name ) );
                    exit();
                }

                // If not object, die
                if ( ! $this->object ) {
                    wp_die( __( 'You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?' ) );
                }

                // If not current user can edit, die
                if ( ! current_user_can( 'edit_item', $this->object_id ) ) {
                    wp_die( __( 'Sorry, you are not allowed to edit this item.' ) );
                }

            } else {
                $this->object_id = ct_insert_object( array() );

                // If not id, return to list
                if ( empty( $this->object_id ) ) {
                    wp_redirect( ct_get_list_link( $ct_table->name ) );
                    exit();
                }

                $this->object = ct_get_object( $this->object_id );

                // If not object, die
                if ( ! $this->object )
                    wp_die( __( 'Unable to create the draft item.' ) );

                // If not current user can create, die
                if ( ! current_user_can( 'create_item', $this->object_id ) ) {
                    wp_die( __( 'Sorry, you are not allowed to create items of this type.' ) );
                }
            }

            $messages = array(
                0 => __( '%s could not be updated.' ),
                1 => __( '%s updated successfully.' ),
            );

            /**
             * Filters the table updated messages.
             *
             * @since 1.0.0
             *
             * @param array $messages Post updated messages. For defaults @see $messages declarations above.
             */
            $messages = apply_filters( 'ct_table_updated_messages', $messages );

            // Setup screen message
            if ( isset($_GET['message']) ) {

                $_GET['message'] = absint( $_GET['message'] );

                if ( isset($messages[$_GET['message']]) )
                    $this->message = sprintf( $messages[$_GET['message']], $ct_table->labels->singular_name );

            }

            wp_enqueue_script( 'post' );

            if ( wp_is_mobile() ) {
                wp_enqueue_script( 'jquery-touch-punch' );
            }

            // Register submitdiv metabox
            add_meta_box( 'submitdiv', __( 'Save Changes' ), array( $this, 'submit_meta_box' ), $ct_table->name, 'side', 'core' );

            /**
             * Fires after all built-in meta boxes have been added.
             *
             * @since 1.0.0
             *
             * @param string  $post_type Post type.
             * @param WP_Post $post      Post object.
             */
            do_action( 'add_meta_boxes', $ct_table->name, $this->object );

            /**
             * Fires after all built-in meta boxes have been added, contextually for the given post type.
             *
             * The dynamic portion of the hook, `$post_type`, refers to the post type of the post.
             *
             * @since 1.0.0
             *
             * @param WP_Post $post Post object.
             */
            do_action( "add_meta_boxes_{$ct_table->name}", $this->object );

            /**
             * Fires after meta boxes have been added.
             *
             * Fires once for each of the default meta box contexts: normal, advanced, and side.
             *
             * @since 1.0.0
             *
             * @param string  $post_type Type of the object.
             * @param string  $context   string  Meta box context.
             * @param WP_Post $object    The object.
             */
            do_action( 'do_meta_boxes', $ct_table->name, 'normal', $this->object );
            /** This action is documented in wp-admin/edit-form-advanced.php */
            do_action( 'do_meta_boxes', $ct_table->name, 'advanced', $this->object );
            /** This action is documented in wp-admin/edit-form-advanced.php */
            do_action( 'do_meta_boxes', $ct_table->name, 'side', $this->object );

            //add_screen_option( 'layout_columns', array( 'max' => $this->columns, 'default' => $this->columns ) );
        }

        /**
         * Screen settings text displayed in the Screen Options tab.
         *
         * @param string    $screen_settings    Screen settings.
         * @param WP_Screen $screen             WP_Screen object.
         */
        public function screen_settings( $screen_settings, $screen ) {

            $this->render_meta_boxes_preferences();
            $this->render_screen_layout();

        }

        /**
         * Render the meta boxes preferences.
         *
         * @since 1.0.0
         *
         * @global array $wp_meta_boxes
         */
        public function render_meta_boxes_preferences() {

            global $wp_meta_boxes, $ct_table;

            // TODO: Forced to place it here until fix issue with load-{pagehook}

            /** This action is documented in wp-admin/edit-form-advanced.php */
            do_action( 'add_meta_boxes', $ct_table->name, $this->object );

            /** This action is documented in wp-admin/edit-form-advanced.php */
            do_action( "add_meta_boxes_{$ct_table->name}", $this->object );

            /** This action is documented in wp-admin/edit-form-advanced.php */
            do_action( 'do_meta_boxes', $ct_table->name, 'normal', $this->object );
            /** This action is documented in wp-admin/edit-form-advanced.php */
            do_action( 'do_meta_boxes', $ct_table->name, 'advanced', $this->object );
            /** This action is documented in wp-admin/edit-form-advanced.php */
            do_action( 'do_meta_boxes', $ct_table->name, 'side', $this->object );

            if ( ! isset( $wp_meta_boxes[ $ct_table->name ] ) ) {
                return;
            }
            ?>

            <fieldset class="metabox-prefs">
                <legend><?php _e( 'Boxes' ); ?></legend>
                <?php meta_box_prefs( $ct_table->name ); ?>
            </fieldset>

            <?php
        }

        /**
         * Render the option for number of columns on the page
         *
         * @since 1.0.0
         */
        public function render_screen_layout() {

            if ( $this->columns <= 1 ) {
                return;
            }

            $screen_layout_columns = get_current_screen()->get_columns();

            if( ! $screen_layout_columns ) {
                $screen_layout_columns = $this->columns;
            }

            $num = $this->columns;

            ?>
            <fieldset class='columns-prefs'>
                <legend class="screen-layout"><?php _e( 'Layout' ); ?></legend><?php
                for ( $i = 1; $i <= $num; ++$i ):
                    ?>
                    <label class="columns-prefs-<?php echo $i; ?>">
                        <input type='radio' name='screen_columns' value='<?php echo esc_attr( $i ); ?>'
                            <?php checked( $screen_layout_columns, $i ); ?> />
                        <?php printf( _n( '%s column', '%s columns', $i ), number_format_i18n( $i ) ); ?>
                    </label>
                    <?php
                endfor; ?>
            </fieldset>
            <?php
        }

        public function submit_meta_box( $object ) {

            global $ct_table;

            /**
             * Fires inside the submit meta box.
             *
             * @since 1.0.0
             *
             * @param object        $object         Object.
             * @param CT_Table      $ct_table       CT Table object.
             * @param bool          $editing        True if edit screen, false if is adding a new one.
             * @param CT_Edit_View  $view           Edit view object.
             */
            do_action( "ct_{$ct_table->name}_edit_screen_submit_meta_box", $object, $ct_table, $this->editing, $this );

            $submit_label = __( 'Add' );

            if( $this->editing ) {
                $submit_label = __( 'Update' );
            }

            /**
             * Filter to override the submit button label.
             *
             * @since 1.0.0
             *
             * @param string        $submit_label   The submit label.
             * @param object        $object         Object.
             * @param CT_Table      $ct_table       CT Table object.
             * @param bool          $editing        True if edit screen, false if is adding a new one.
             * @param CT_Edit_View  $view           Edit view object.
             */
            $submit_label = apply_filters( "ct_{$ct_table->name}_edit_screen_submit_label", $submit_label, $object, $ct_table, $this->editing, $this )

            ?>

            <div style="padding: 10px;">

                <?php submit_button( $submit_label, 'primary large', 'ct-save', false ); ?>

            </div>

            <?php
        }

        public function save() {

            global $ct_registered_tables, $ct_table;

            // If not CT object, die
            if ( ! $ct_table )
                wp_die( __( 'Invalid item type.' ) );

            // If not CT object allow ui, die
            if ( ! $ct_table->show_ui ) {
                wp_die( __( 'Sorry, you are not allowed to edit items of this type.' ) );
            }

            $object_data = &$_POST;

            unset( $object_data['ct-save'] );

            $success = ct_update_object( $object_data );

            $primary_key = $ct_table->db->primary_key;
            $object_id = $_POST[$primary_key];

            $location = add_query_arg( array( $primary_key => $object_id ), $this->get_link() );

            if( $success ) {
                $location = add_query_arg( array( 'message' => 1 ), $location );
            } else {
                $location = add_query_arg( array( 'message' => 0 ), $location );
            }

            wp_redirect( $location );
            exit;

        }

        public function render() {

            global $ct_registered_tables, $ct_table;

            $this->init();

            if( $this->editing ) {
                $title = $ct_table->labels->edit_item;
                $new_url = $ct_table->views->add->get_link();
            } else {
                $title = $ct_table->labels->add_new_item;
            }

            ?>

            <div class="wrap">

                <h1 class="wp-heading-inline"><?php echo $title; ?></h1>

                <?php
                if ( isset( $new_url ) && current_user_can( $ct_table->cap->create_items ) ) {
                    echo ' <a href="' . esc_url( $new_url ) . '" class="page-title-action">' . esc_html( $ct_table->labels->add_new_item ) . '</a>';
                }
                ?>

                <hr class="wp-header-end">

                <?php if ( $this->message ) : ?>
                    <div id="message" class="updated notice notice-success is-dismissible"><p><?php echo $this->message; ?></p></div>
                <?php endif; ?>

                <form name="ct_edit_form" action="" method="post" id="ct_edit_form">

                    <input type="hidden" id="object_id" name="<?php echo $ct_table->db->primary_key; ?>" value="<?php echo $this->object_id; ?>">

                    <?php
                    /**
                     * Fires at the beginning of the edit form.
                     *
                     * At this point, the required hidden fields and nonces have already been output.
                     *
                     * @since 1.0.0
                     *
                     * @param stdClass $object Object.
                     */
                    do_action( 'ct_edit_form_top', $this->object ); ?>

                    <div id="poststuff">

                        <div id="post-body" class="metabox-holder columns-<?php echo get_current_screen()->get_columns() === 1 || $this->columns === 1 ? '1' : '2'; ?>">

                            <div id="postbox-container-1" class="postbox-container">

                                <?php do_meta_boxes( $ct_table->name, 'side', $this->object ); ?>

                            </div>

                            <div id="postbox-container-2" class="postbox-container">

                                <?php do_meta_boxes( $ct_table->name, 'normal', $this->object ); ?>

                                <?php do_meta_boxes( $ct_table->name, 'advanced', $this->object ); ?>

                            </div>

                        </div><!-- /post-body -->

                        <br class="clear" />

                    </div><!-- /poststuff -->

                    <?php
                    /**
                     * Fires at the end of the edit form.
                     *
                     * @since 1.0.0
                     *
                     * @param stdClass $object Object.
                     */
                    do_action( 'ct_edit_form_bottom', $this->object ); ?>

                </form>

            </div>

            <?php
        }

    }

endif;