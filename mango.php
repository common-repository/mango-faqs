<?php
/*
Plugin Name: Mango FAQs
Plugin URI: https://wordpress.org/plugins/mango-faqs/
Description: Automatically answer emails using your FAQs.
Author: Justin Comino
Version: 1.0
Author URI: http://justincomino.com
*/

//Licensed under GPLv2

// creates the mango database table for questions
// since it uses dbDelta, it wont create a new db if one exists
function mango_install (){
	global $wpdb;
	$table_name = $wpdb->prefix . "mango"; 
	$charset_collate = $wpdb->get_charset_collate();
	
	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  question varchar(100) NOT NULL,
	  answer varchar(100) NOT NULL,
	  UNIQUE KEY id (id)
	) $charset_collate;";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

// run "mango_install" function when the plugin is activated
register_activation_hook( __FILE__, 'mango_install' );

// inserts a question into the database
function mango_insert_question($question, $answer){
	// TODO: check if question already exists
	global $wpdb;
	$table_name = $wpdb->prefix . 'mango';
	$wpdb->insert( 
		$table_name, 
		array(
			'question' => $question, 
			'answer' => $answer
		)
	);
}

// deletes question based on id
function mango_delete_question($id){
	global $wpdb;
	$table_name = $wpdb->prefix . 'mango';
	$wpdb->delete( $table_name, array( 'id' => $id ) );
}

// displays the "mango settings" page
function mango_display_options() {
	
	// note: this works because questions are added/deleted BEFORE being displayed below
	if($_POST['addquestion'] || $_POST['addanswer'] || $_POST['deleteid']){
		$addquestion = mango_validate_sanitize($_POST['addquestion'], 'text');
		$addanswer = mango_validate_sanitize($_POST['addanswer'], 'text');
		$deleteid = mango_validate_sanitize($_POST['deleteid'], 'text');
	}
	
	if(($addquestion && $addanswer) == TRUE){ mango_insert_question($addquestion, $addanswer); };
	if($deleteid){ mango_delete_question($deleteid); };
	?>
	<div class="wrap">
		<h2>Mango Settings</h2>
		<br>
		<p style="float:left;">The contact form shortcode is:&nbsp;&nbsp;&nbsp;<pre>[mango-contact]</pre></p>
		<br>
		<p style="float:left;">To display your FAQs, use:&nbsp;&nbsp;&nbsp;<pre>[mango-faq]</pre></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'mango-settings-group' ); ?>
				<?php do_settings_sections( 'mango-settings-group' ); ?>
				<table class="form-table" style="width:500px">
					<tr valign="top">
						<th scope="row">Admin Email<br><small style="font-weight:normal;">(where the contact form is sent)</small></th>
						<td><input type="text" name="mango_admin_email" value="<?php echo esc_attr( get_option('mango_admin_email') ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		<hr>
		<h2>Mango FAQ</h2>
		<table class=" widefat fixed striped">
			<thead>
				<tr>
					<td class="check-column">
					</td>
					<th>
						<a>
							<span>Question / Answer</span>
						</a>
					</th>
					<th class="column-date">
						<a>
							<span>ID</span>
						</a>
					</th>
				</tr>
			</thead>
			
			<tbody id="the-list">
				<?php
				wp_enqueue_script('jquery');
				$results = get_mango_faqs();
				
				// TODO: order these questions by the ID number
				foreach($results as $row){
					//$question = stripslashes(str_replace('"','',(str_replace("'","",$row->question))));
					$question = $row->question;
					$answer = $row->answer;
					$id = $row->id;
					echo '<tr><th class="check-column"><span class="xbutt">x</span></th><td><strong><a>';
					echo $question;
					echo '</a><br>';
					echo $answer;
					echo '</strong></td><td><span class="quid">';
					echo $id;
					echo '</span></td></tr>';
				}
				?>
				<tr>
					<th class="check-column"></th>
					<td>
						<form id="entryform" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=mango-settings">
							<input type="text" name="addquestion" placeholder="Question">
							<br>
							<input type="text" name="addanswer" placeholder="Answer">
							<br>
							<input type="submit" class="button button-primary" value="Save Question">
						</form>
					</td>
					<td></td>
				</tr>
			</tbody>
			
			<tfoot>
				<tr>
					<td class="check-column">
					</td>
					<th>
						<a>
							<span>Question / Answer</span>
						</a>
					</th>
					<th class="column-date">
						<a>
							<span>ID</span>
						</a>
					</th>
				</tr>
			</tfoot>
		</table>
	</div>
	<form id="deleteform" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=mango-settings">
		<input id="deleteinput" type="hidden" name="deleteid">
	</form>
	<?php
}


// loads the js and css files
function load_mango_script() {
wp_enqueue_script( 'script', plugins_url('javascript.js', __FILE__), array('jquery'));
wp_register_style( 'mango', plugins_url( 'mango/style.css' ) );
wp_enqueue_style( 'mango' );
}

add_action('init', 'load_mango_script');

// add the mango options page using function "mango_display_options"
function mango_menu() {
	add_options_page( 'Mango FAQs', 'Mango FAQs', 'manage_options', 'mango-settings', 'mango_display_options' );
}

// add the above as a menu item and page
add_action( 'admin_menu', 'mango_menu' );

// register the dispalyed options with the database
function register_mango_settings() {
	//register our settings to the database
	register_setting( 'mango-settings-group', 'mango_admin_email' );
}

//call register settings function
add_action( 'admin_init', 'register_mango_settings' );

// forwards customers message to the website admin
function forward_mango_message($email, $message){
	// TODO: allow user to specify the number of FAQs to show
	$number_of_faqs = 5;
	
	//fetch the proper email address, forward question to address
	$mango_forward_email = esc_attr(get_option('mango_admin_email'));
	$subject = "Forwarded Question from Mango FAQs, from $email";
	wp_mail($mango_forward_email, $subject, $message);
	
	//matching the message with relevant FAQs for customer email	
	$master_array = get_packaged_mangos();
	
	$sourcetext = $master_array[0];
	$ids = $master_array[1];
	
	$query = remove_common_mango_words($message);
	$match_results = get_similar_documents($query,$sourcetext);
	$keyarray = array_keys($match_results);
	$topresults = array_slice($keyarray, 0, $number_of_faqs, true);
	
	$finalarray = array();
	
	foreach($topresults as $result){
		$tempvalue = $ids[$result];
		array_push($finalarray,$tempvalue);
	}
	
	send_mango_faqs($email, $finalarray);
}

// replies to the customer with the juicy mango message containing juicy FAQs
function send_mango_faqs($email, $topresults){
	global $wpdb;
	$table_name = $wpdb->prefix . "mango";
	$subject = "We have received your question.";
	$content = "Please allow a few days for a response. In the meantime, here are several questions that may answer your query.";
	$content .= "\r\n";
	$content .= "\r\n";
	foreach($topresults as $value){
		$forerunner = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $value");
		$question = $forerunner->question;
		$question = mb_strimwidth($question, 0, 65, "...");
		$answer = $forerunner->answer;
		$answer = mb_strimwidth($answer, 0, 65, "...");
		$content .= "Q.  ";
		$content .= $question;
		$content .= "\r\n";
		$content .= "A.  ";
		$content .= $answer;
		$content .= "\r\n";
		$content .= "\r\n";
	}
	wp_mail($email, $subject, $content);
}

// removes "stop words" to make interpretation easier
function remove_common_mango_words($input){
	$wordarray = array('a','able','about','above','abroad','according','accordingly','across','actually','adj','after','afterwards','again','against','ago','ahead','ain\'t','all','allow','allows','almost','alone','along','alongside','already','also','although','always','am','amid','amidst','among','amongst','an','and','another','any','anybody','anyhow','anyone','anything','anyway','anyways','anywhere','apart','appear','appreciate','appropriate','are','aren\'t','around','as','a\'s','aside','ask','asking','associated','at','available','away','awfully','b','back','backward','backwards','be','became','because','become','becomes','becoming','been','before','beforehand','begin','behind','being','believe','below','beside','besides','best','better','between','beyond','both','brief','but','by','c','came','can','cannot','cant','can\'t','caption','cause','causes','certain','certainly','changes','clearly','c\'mon','co','co.','com','come','comes','concerning','consequently','consider','considering','contain','containing','contains','corresponding','could','couldn\'t','course','c\'s','currently','d','dare','daren\'t','definitely','described','despite','did','didn\'t','different','directly','do','does','doesn\'t','doing','done','don\'t','down','downwards','during','e','each','edu','eg','eight','eighty','either','else','elsewhere','end','ending','enough','entirely','especially','et','etc','even','ever','evermore','every','everybody','everyone','everything','everywhere','ex','exactly','example','except','f','fairly','far','farther','few','fewer','fifth','first','five','followed','following','follows','for','forever','former','formerly','forth','forward','found','four','from','further','furthermore','g','get','gets','getting','given','gives','go','goes','going','gone','got','gotten','greetings','h','had','hadn\'t','half','happens','hardly','has','hasn\'t','have','haven\'t','having','he','he\'d','he\'ll','hello','help','hence','her','here','hereafter','hereby','herein','here\'s','hereupon','hers','herself','he\'s','hi','him','himself','his','hither','hopefully','how','howbeit','however','hundred','i','i\'d','ie','if','ignored','i\'ll','i\'m','immediate','in','inasmuch','inc','inc.','indeed','indicate','indicated','indicates','inner','inside','insofar','instead','into','inward','is','isn\'t','it','it\'d','it\'ll','its','it\'s','itself','i\'ve','j','just','k','keep','keeps','kept','know','known','knows','l','last','lately','later','latter','latterly','least','less','lest','let','let\'s','like','liked','likely','likewise','little','look','looking','looks','low','lower','ltd','m','made','mainly','make','makes','many','may','maybe','mayn\'t','me','mean','meantime','meanwhile','merely','might','mightn\'t','mine','minus','miss','more','moreover','most','mostly','mr','mrs','much','must','mustn\'t','my','myself','n','name','namely','nd','near','nearly','necessary','need','needn\'t','needs','neither','never','neverf','neverless','nevertheless','new','next','nine','ninety','no','nobody','non','none','nonetheless','noone','no-one','nor','normally','not','nothing','notwithstanding','novel','now','nowhere','o','obviously','of','off','often','oh','ok','okay','old','on','once','one','ones','one\'s','only','onto','opposite','or','other','others','otherwise','ought','oughtn\'t','our','ours','ourselves','out','outside','over','overall','own','p','particular','particularly','past','per','perhaps','placed','please','plus','possible','presumably','probably','provided','provides','q','que','quite','qv','r','rather','rd','re','really','reasonably','recent','recently','regarding','regardless','regards','relatively','respectively','right','round','s','said','same','saw','say','saying','says','second','secondly','see','seeing','seem','seemed','seeming','seems','seen','self','selves','sensible','sent','serious','seriously','seven','several','shall','shan\'t','she','she\'d','she\'ll','she\'s','should','shouldn\'t','since','six','so','some','somebody','someday','somehow','someone','something','sometime','sometimes','somewhat','somewhere','soon','sorry','specified','specify','specifying','still','sub','such','sup','sure','t','take','taken','taking','tell','tends','th','than','thank','thanks','thanx','that','that\'ll','thats','that\'s','that\'ve','the','their','theirs','them','themselves','then','thence','there','thereafter','thereby','there\'d','therefore','therein','there\'ll','there\'re','theres','there\'s','thereupon','there\'ve','these','they','they\'d','they\'ll','they\'re','they\'ve','thing','things','think','third','thirty','this','thorough','thoroughly','those','though','three','through','throughout','thru','thus','till','to','together','too','took','toward','towards','tried','tries','truly','try','trying','t\'s','twice','two','u','un','under','underneath','undoing','unfortunately','unless','unlike','unlikely','until','unto','up','upon','upwards','us','use','used','useful','uses','using','usually','v','value','various','versus','very','via','viz','vs','w','want','wants','was','wasn\'t','way','we','we\'d','welcome','well','we\'ll','went','were','we\'re','weren\'t','we\'ve','what','whatever','what\'ll','what\'s','what\'ve','when','whence','whenever','where','whereafter','whereas','whereby','wherein','where\'s','whereupon','wherever','whether','which','whichever','while','whilst','whither','who','who\'d','whoever','whole','who\'ll','whom','whomever','who\'s','whose','why','will','willing','wish','with','within','without','wonder','won\'t','would','wouldn\'t','x','y','yes','yet','you','you\'d','you\'ll','your','you\'re','yours','yourself','yourselves','you\'ve','z','zero');
	return preg_replace('/\b('.implode('|',$wordarray).')\b/', "", $input);
}

// packages questions and their corresponding ID's for further processing
function get_packaged_mangos(){
	global $wpdb;
	$table_name = $wpdb->prefix . "mango"; 
	$results = $wpdb->get_results("SELECT * FROM $table_name");
	$master_array = array();
	$text_array = array();
	$id_array = array();
	
	foreach($results as $row){
		$question = $row->question;
		$answer = $row->answer;
		$id = $row->id;
		
		$pre_join = array($question, $answer);
		$joined = join(" ",$pre_join);
		
		array_push($text_array,$joined);
		array_push($id_array,$id);
	}
	array_push($master_array, $text_array);
	array_push($master_array, $id_array);
	
	return $master_array;
}

// retrieves the questions table from the SQL database
function get_mango_faqs(){
	global $wpdb;
	$table_name = $wpdb->prefix . "mango"; 
	$results = $wpdb->get_results("SELECT * FROM $table_name");
	return $results;
}

// sanitizes data by stripping characters and ensuring it is text
function mango_validate_sanitize($untrusted_data, $flag){
	if(is_string($untrusted_data)){
		if($flag == 'text'){
			$safe_data = sanitize_text_field($untrusted_data);
			return $safe_data;
		}
		
		if($flag == 'email'){
			$safe_data = sanitize_email($untrusted_data);
			if(is_email($safe_data)){
				return $safe_data;
			} else {
				return '';
			}
		}
	} else {
		$blank_data = "";
		return $blank_data;
	}
}

function get_corpus_index($corpus = array(), $separator=' ') {
    $dictionary = array();
    $doc_count = array();

    foreach($corpus as $doc_id => $doc) {

        $terms = explode($separator, $doc);
        $doc_count[$doc_id] = count($terms);

        foreach($terms as $term) {

            if(!isset($dictionary[$term])) {
                $dictionary[$term] = array('document_frequency' => 0, 'postings' => array());
            }
            if(!isset($dictionary[$term]['postings'][$doc_id])) {
                $dictionary[$term]['document_frequency']++;
                $dictionary[$term]['postings'][$doc_id] = array('term_frequency' => 0);
            }
            $dictionary[$term]['postings'][$doc_id]['term_frequency']++;
        }
    }
    return array('doc_count' => $doc_count, 'dictionary' => $dictionary);
}

// returns a numerical score on which elements of an array are most relevant to a string
function get_similar_documents($query='', $corpus=array(), $separator=' '){

    $similar_documents=array();

    if($query!=''&&!empty($corpus)){
        $words=explode($separator,$query);
        $corpus=get_corpus_index($corpus, $separator);
        $doc_count=count($corpus['doc_count']);

        foreach($words as $word) {

            if(isset($corpus['dictionary'][$word])){

                $entry = $corpus['dictionary'][$word];

                foreach($entry['postings'] as $doc_id => $posting) {

                    //get term frequency–inverse document frequency
                    $score=$posting['term_frequency'] * log($doc_count + 1 / $entry['document_frequency'] + 1, 2);

                    if(isset($similar_documents[$doc_id])){
                        $similar_documents[$doc_id]+=$score;
                    }
                    else{
                        $similar_documents[$doc_id]=$score;
                    }
                }
            }
        }

        // normalizes the length
        foreach($similar_documents as $doc_id => $score) {
            $similar_documents[$doc_id] = $score/$corpus['doc_count'][$doc_id];

        }
        // sort from high to low
        arsort($similar_documents);
    }
    return $similar_documents;
}

//adds the shortcodes
function mango_contact_shortcode(){
	?>
	<form id="mango-contact" action="" method="post">
		Email Address: <input id="mango-input" type="text" name="mango_email"><br>
		Message: <textarea id="mango-text" name="mango_message"></textarea>
		<input type="submit" value="Send Message">
	</form>
	<?php
	// if these variables have been set
	if($_POST['mango_email'] || $_POST['mango_name'] || $_POST['mango_message']){
		$email = mango_validate_sanitize($_POST['mango_email'], 'email');
		$message = mango_validate_sanitize($_POST['mango_message'], 'text');
	}
	
	if(($email && $message) == TRUE){
		forward_mango_message($email, $message);
	}
}

add_shortcode('mango-contact', 'mango_contact_shortcode');

// displays the FAQs on the frontend
function mango_faq_shortcode(){
	$question_array = get_mango_faqs();
	
	foreach($question_array as $value){
		echo '<br>';
		echo $value->question;
		echo '<br>';
		echo $value->answer;
		echo '<br>';
		echo '<br>';
	}
}

add_shortcode('mango-faq', 'mango_faq_shortcode');
?>