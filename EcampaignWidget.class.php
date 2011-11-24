<?php

/*

class : EcampaignWidget
Author: John Ackers

Displays a list of people that have participated in a
campaign action.  It can be driopped into a sidebar.


*/

include_once dirname(__FILE__) . '/EcampaignLog.class.php';
include_once dirname(__FILE__) . '/Ecampaign.class.php';

/**
 * EcampaignWidget Class
 */
class EcampaignWidget extends WP_Widget {
	/** constructor */
	function __construct() {
		parent::WP_Widget( /* Base ID */'ecampaign_widget', /* Name */'Ecampaign Activity', array( 'description' => 'List recently completed campaign actions'));
	}

	/** @see WP_Widget::widget */
	function widget( $args, $instance ) {
		extract( $args );
//		$title = apply_filters( 'ecampaign', $instance['title');

		$log = new EcampaignLog;
		$limit = 10 ;
		$filterByPostID = $instance['postID'];
		$rows = $log->getRecentActivists($filterByPostID, $limit);
		if (count($rows) == 0)
		  return ;

    $earliestDate = $rows[count($rows)-1]['stamp'];
    if (false)
    {
      $interval = self::intervalAsString($earliestDate); // doesn't work on PHP < 5.3
      $topLine = "<div>In the last $interval</div>";
    }
    else
    {
      $topLine = "";
    }

    echo $before_widget;
		echo "<div class='widget-title'><h3>".$instance['title']."</h3></div>";
    echo $topLine ;
    echo "<ul>";
		foreach ($rows as $row)
		{
		  // get the first name, remove spaces (need to get last name
      $num = preg_match_all('$\s*([a-zA-Z]*)$', $row[Ecampaign::sVisitorName], $nameMatches);
      $firstName = $nameMatches[1][0];
      // get all the lines that don't have digits and end in a comma
      // we don't want to show a street address or apartment/flat number
		  $num = preg_match_all('$\s*([a-zA-Z\s]*\s*),$', $row['address'], $townMatches);
		  $num = count($townMatches[1]);
		  $town = $num > 1 ? ", ".$townMatches[1][$num-1] : "" ;  // take last line if two or more lines in address

		  $postID = $row[Ecampaign::sPostID] ;
			if ($filterByPostID > 0)
			  $line = "<li>$firstName$town</li>";
		  else
		    $title = get_the_title($postID);
		    $line = "<li>$firstName$town<br/><a href='".get_permalink($postID)."'>".get_the_title($postID)."</a></li>";
			echo $line ;
		}
		echo "</ul>";
		echo $instance['body'] ;
    echo $after_widget;
	}

	private static function intervalAsString($earliestDate)
	{
    $timeEarliest = new DateTime(); //$timeEarliest->__construct(null);
    $timeEarliest->setTimestamp($earliestDate);
    $timeNow = new DateTime();      $timeNow->__construct(null);
    $diff = $timeEarliest->diff($timeNow);
    if ($diff->y > 0)
      return (1 + $diff->y)." ".__('years');
    if ($diff->m > 0)
      return (1 + $diff->m)." ".__('months');
    if ($diff->d > 0)
      return (1 + $diff->d)." ".__('days');
    if ($diff->h > 0)
      return (1 + $diff->h)." ".__('hours');
    if ($diff->m > 0)
      return (1 + $diff->m)." ".__('minutes');
    if ($diff->s > 0)
      return (1 + $diff->s)." ".__('seconds');
    return __('second');
	}


	/** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
    $instance['title'] = strip_tags($new_instance['title']);
		$instance['limit'] = strip_tags($new_instance['limit']);
    $instance['postID'] = strip_tags($new_instance['postID']);
    $instance['body'] = strip_tags($new_instance['body']);
    return $instance;
	}

	/** @see WP_Widget::form */
	function form( $instance ) {
		if ( $instance ) {
      $title = esc_attr( $instance[ 'title' ] );
		  $limit = esc_attr( $instance[ 'limit' ] );
      $postID = esc_attr( $instance[ 'postID' ] );
      $body = esc_attr( $instance[ 'body' ] );
		}
		else {
      $title =  __( "Latest actions", 'text_domain' );
		  $limit = 8 ;
		  $body = __("");
		}
		?>
		<p>
		<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
    <label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number of rows:'); ?></label>
    <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" />
    <label for="<?php echo $this->get_field_id('postID'); ?>"><?php _e('Post ID: (leave blank to show all)'); ?></label>
    <input class="widefat" id="<?php echo $this->get_field_id('postID'); ?>" name="<?php echo $this->get_field_name('postID'); ?>" type="text" value="<?php echo $postID; ?>" />
    <label for="<?php echo $this->get_field_id('body'); ?>"><?php _e('Text below list:'); ?></label>
    <input class="widefat" id="<?php echo $this->get_field_id('body'); ?>" name="<?php echo $this->get_field_name('body'); ?>" type="text" value="<?php echo $body; ?>" />
		</p>
		<?php
	}

} // class EcampaignWidget


?>