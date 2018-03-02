<?php
/**
 * Plugin Name:       Importador Mágico do Alvino
 * Plugin URI:        https://github.com/darciro
 * Description:       @TODO, Para utilizar esse plugin é necessário ter instalado a extensão Zip do PHP (http://php.net/manual/en/zip.installation.php)
 * Version:           1.0.0
 * Author:            Ricardo Carvalho
 * Author URI:        https://github.com/darciro/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if( ! class_exists('AlvinosMagicImporter') ) :

	class AlvinosMagicImporter{

		public function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'register_ami_styles' ) );
			add_shortcode( 'ami-form', array( $this, 'ami_shortcodes' ) );
			add_action( 'wp_ajax_import_user', array( $this, 'ami_import_user' ) );
			add_action( 'wp_ajax_nopriv_import_user', array( $this, 'ami_import_user' ) );
		}

		// Register our public styles
		public function register_ami_styles() {
			wp_register_style( 'ami-styles', plugins_url( 'ge-wp/assets/ami-styles.css' ) );
			wp_enqueue_style( 'ami-styles' );
		}

		// Register our public scripts
		public function register_ami_scripts() {
			wp_register_script( 'ami-scripts', plugins_url( 'ge-wp/assets/ami-scripts.js' ) );
			wp_enqueue_script( 'ami-scripts' );
		}

		// check the current post for the existence of a short code
		public function has_shortcode($shortcode = '') {
			$post_to_check = get_post(get_the_ID());
			// false because we have to search through the post content first
			$found = false;
			// if no short code was provided, return false
			if (!$shortcode) {
				return $found;
			}
			// check the post content for the short code
			if ( stripos($post_to_check->post_content, '[' . $shortcode) !== false ) {
				// we have found the short code
				$found = true;
			}

			// return our final results
			return $found;
		}

		public function ami_import_user () {
			$response = array(
				'success' => false,
				'message' => ''
			);
			$imported_users = [];
			$user_data = [];
			$i = 0;
			foreach ( $_POST['user_data'] as $data ) {

				$user_data['first_name'] = esc_attr($data['nome']);
				$user_data['display_name'] = esc_attr($data['nome']);
				$user_data['user_login'] = esc_attr($data['matricula']);
				$user_data['user_email'] = esc_attr( strtolower($data['matricula'] ) ) . '@imported.com';
				$user_data['user_pass'] = esc_attr($data['nome']);

				$user_data['matricula'] = esc_attr( $data['matricula'] );
				$user_data['data_nascimento'] = esc_attr( $data['data_nascimento'] );
				$user_data['codigo_comissao'] = esc_attr( $data['codigo_comissao'] );
				$user_data['nome_comissao'] = esc_attr( $data['nome_comissao'] );
				$user_data['prefixo'] = esc_attr( $data['prefixo'] );
				$user_data['nome_dependencia'] = esc_attr( $data['nome_dependencia'] );

				// print_r( $user_data ) ;
				if ( username_exists( $user_data['user_login'] ) ) {
				    // echo 'Usuário já existe' . $user_data['user_login'];
				    break;
                }

				$register_user = wp_insert_user($user_data);
				if ( !is_wp_error($register_user) ) {
					add_user_meta( $register_user, '_user_matricula', $user_data['matricula'], true );
					add_user_meta( $register_user, '_user_data_nascimento', $user_data['data_nascimento'], true );
					add_user_meta( $register_user, '_user_codigo_comissao', $user_data['codigo_comissao'], true );
					add_user_meta( $register_user, '_user_nome_comissao', $user_data['nome_comissao'], true );
					add_user_meta( $register_user, '_user_prefixo', $user_data['prefixo'], true );
					add_user_meta( $register_user, '_user_nome_dependencia', $user_data['nome_dependencia'], true );

					$response['success'] = true;
					$response['message'] = '<li class="list-group-item">Usuário: '. $user_data['first_name'] .' (Matrícula: '. $user_data['matricula'] .'), cadastrado com sucesso.</li>';

					$imported_users[$i]['nome'] = $data['nome'];
					$imported_users[$i]['matricula'] = $data['matricula'];

					$i++;
				} else {
					$response['message'] = '<li class="list-group-item">Erro com o usuário: '. $user_data['first_name'] .' (Matrícula: '. $user_data['matricula'] .'). Descrição: '. $register_user->get_error_message() .'</li>';
				}
			}
			echo json_encode($imported_users);

			// var_dump($user_data);

			/* if (username_exists($user_data['user_login'])) {
				$response['message'] = '<li class="list-group-item">Erro com o usuário: '. $user_data['first_name'] .' (Matrícula: '. $user_data['matricula'] .'). Descrição: Este nome de usuário já existe.</li>';
				die();
            } */



			// echo json_encode($response);
			die();
		}

		public function ami_shortcodes() {
			$importer = include plugin_dir_path( __FILE__ ) . 'inc/bootstrap.php';
			if( !@$importer ){
				echo 'Ops...houve um erro durante o carregamento dos dados de configuração com o banco de dados.';
				return;
			}

			ob_start();

			if( !empty($_GET['ami-spreadsheet-id']) && empty($_GET['ami-importer']) ){
				$parsed = parse_url( wp_get_attachment_url( $_GET['ami-spreadsheet-id'] ) );
				$url    = dirname( $parsed [ 'path' ] ) . '/' . rawurlencode( basename( $parsed[ 'path' ] ) );
				$url = ABSPATH .  $url;
				$Reader = new SpreadsheetReader( $url);
				$Sheets = $Reader->Sheets();
				echo '<p>Dados da planilha</p>';
				echo '<pre style="max-height: 300px; overflow-x: hidden; overflow-y: auto; background: #eee; padding: 15px">';
				foreach ($Sheets as $Index => $Name)
				{
					$Reader->ChangeSheet($Index);
					foreach ($Reader as $Row) {
						print_r($Row);
					}
				}
				echo '</pre>'; ?>

				<form action="<?php echo get_the_permalink(); ?>">
					<input type="hidden" name="ami-spreadsheet-id" id="ami-spreadsheet-id" value="<?php echo $_GET['ami-spreadsheet-id']; ?>">
					<div class="form-group text-right">
						<input type="submit" name="ami-importer" value="Começar importação" class="btn btn-primary mb-2">
					</div>
				</form>

			<?php } elseif( !empty($_GET['ami-spreadsheet-id']) && !empty($_GET['ami-importer']) ) {

				$parsed = parse_url( wp_get_attachment_url( $_GET['ami-spreadsheet-id'] ) );
				$url    = dirname( $parsed [ 'path' ] ) . '/' . rawurlencode( basename( $parsed[ 'path' ] ) );
				$url = ABSPATH .  $url;
				$Reader = new SpreadsheetReader( $url);
				$Sheets = $Reader->Sheets();
				$list_of_users = [];

				$i = 0;
				foreach ($Sheets as $Index => $Name) {
					$Reader->ChangeSheet($Index);
					foreach ($Reader as $Row) {
						$list_of_users[$i]['nome'] = $Row[0];
						$list_of_users[$i]['matricula'] = $Row[1];
						$list_of_users[$i]['data_nascimento'] = $Row[2];
						$list_of_users[$i]['codigo_comissao'] = $Row[3];
						$list_of_users[$i]['nome_comissao'] = $Row[4];
						$list_of_users[$i]['prefixo'] = $Row[5];
						$list_of_users[$i]['nome_dependencia'] = $Row[6];
						$i++;
					}
				}
				// Remove o cabecalho da planilha
				unset( $list_of_users[0] ); ?>

				<ul id="imported-users" class="list-group"></ul>
				<script>
                    (function ($) {
                        $(document).ready(function () {
                            userData = {
                                action: 'import_user',
                                user_data: <?php echo json_encode($list_of_users); ?>
                            };
                            $.ajax({
                                url : '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                                data : userData,
                                type : 'POST',
                                beforeSend: function () {
                                    var li = '<li class="list-group-item active loading">Importando, por favor aguarde...</li>';
                                    $('#imported-users').append(li);
                                },
                                success : function( data ){
                                    $.each(JSON.parse(data), function (i, user) {
                                        var li = '<li class="list-group-item">Usuário: '+ user.nome +' (Matrícula: '+ user.matricula +'), cadastrado com sucesso.</li>';
                                        $('#imported-users').append(li);
                                    });

                                    var li = '<li class="list-group-item active"><b>Importação realizada com sucesso.</b></li>';
                                    $('#imported-users').append(li);
                                    $('#imported-users .loading').remove();
                                },
                                error: function(error){
                                    console.error( 'ERROR: ', error );
                                }
                            });

                        })
                    })(jQuery);
				</script>

			<?php } else { ?>
				<form action="<?php echo get_the_permalink(); ?>">
					<h2>Importador Mágico do Alvino</h2>
					<div class="form-group">
						<label for="ami-spreadsheet-id">Planilha de importação - ID da Mídia (Media Attachment)</label><br>
						<input class="form-control" type="number" name="ami-spreadsheet-id" id="ami-spreadsheet-id">
					</div>
					<div class="form-group text-right">
						<button type="submit" class="btn btn-primary mb-2">Começar importação</button>
					</div>
				</form>
			<?php } ?>

			<?php return ob_get_clean();

		}

	}

	// Initialize our plugin
	$ami = new AlvinosMagicImporter();

endif;