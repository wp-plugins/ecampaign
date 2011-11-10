/**
 * 
 * ecampaign admin js support 
 * handles reveals on log page
 */

jQuery(document).ready(function() { 
  
    /**
     * when hovering over text block, show the 'more' word 
     */
  jQuery('.infoMore').closest('td').hover( 
      function(event) { jQuery(this).find('.infoMore').show(); },    
      function(event) { jQuery(this).find('.infoMore').hide(); }  
  );       
    /**
     * reveal additional text when user clicks inside the infoBlock 
     * by adding a second row underneath the row on display
     */  
  jQuery('.infoBlock').closest('td').click( 
    function(event) { 
      var tr = jQuery(this).closest('tr');  
      var td = tr.find('td');
      var extraInfo = tr.next('.extraInfo');
      if (extraInfo.length > 0) 
      {
        extraInfo.remove();
        td.css('border-width', '1px 0');  // restore the border 
        return ;
      }
      var html = tr.find('.infoBlock').html();
      tr.after('<tr class="extraInfo"><td colspan="' + td.length +'">' + html + '</td></tr>'); 
      td.css('border-width', '0 0');          
    }    
  );  
});