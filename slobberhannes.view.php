<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Slobberhannes implementation : © Sam Rush <swrush98@yahoo.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * slobberhannes.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in slobberhannes_slobberhannes.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */
  
require_once( APP_BASE_PATH."view/common/game.view.php" );
  
class view_slobberhannes_slobberhannes extends game_view
{
    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "slobberhannes";
    }
    
  	function build_page( $viewArgs )
  	{		
  	    // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count( $players );

        /*********** Place your code below:  ************/

        // Arrange players so that I am on south
        $player_to_dir = $this->game->getPlayersToDirectionString();

        $this->page->begin_block( "slobberhannes_slobberhannes", "player" );
        foreach( $player_to_dir as $player_id => $dir )
        {
            $this->page->insert_block( "player", array( "PLAYER_ID" => $player_id,
                                                        "PLAYER_NAME" => $players[$player_id]['player_name'],
                                                        "PLAYER_COLOR" => $players[$player_id]['player_color'],
                                                        "DIR" => $dir ) );
        }
        
        $table_size_class = 'playertables_normal';
        if ($players_nbr == 5)
        {
            $table_size_class = 'playertables_5p';
        }
        else if ($players_nbr == 6)
        {
            $table_size_class = 'playertables_6p';
        }
        $this->tpl['TABLE_SIZE_CLASS'] = $table_size_class;
        $this->tpl['MY_HAND'] = self::_("My hand");

        /*
        
        // Examples: set the value of some element defined in your tpl file like this: {MY_VARIABLE_ELEMENT}

        // Display a specific number / string
        $this->tpl['MY_VARIABLE_ELEMENT'] = $number_to_display;

        // Display a string to be translated in all languages: 
        $this->tpl['MY_VARIABLE_ELEMENT'] = self::_("A string to be translated");

        // Display some HTML content of your own:
        $this->tpl['MY_VARIABLE_ELEMENT'] = self::raw( $some_html_code );
        
        */
        
        /*
        
        // Example: display a specific HTML block for each player in this game.
        // (note: the block is defined in your .tpl file like this:
        //      <!-- BEGIN myblock --> 
        //          ... my HTML code ...
        //      <!-- END myblock --> 
        

        $this->page->begin_block( "slobberhannes_slobberhannes", "myblock" );
        foreach( $players as $player )
        {
            $this->page->insert_block( "myblock", array( 
                                                    "PLAYER_NAME" => $player['player_name'],
                                                    "SOME_VARIABLE" => $some_value
                                                    ...
                                                     ) );
        }
        
        */



        /*********** Do not change anything below this line  ************/
  	}
}
