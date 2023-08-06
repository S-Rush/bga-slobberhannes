<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * Slobberhannes implementation : © <Your name here> <Your email address here>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * slobberhannes.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );

const SUIT_SPADES = 1;
const SUIT_HEARTS = 2;
const SUIT_CLUBS = 3;
const SUIT_DIAMONDS = 4;

const VALUE_JACK = 11;
const VALUE_QUEEN = 12;
const VALUE_KING = 13;
const VALUE_ACE = 14;


class Slobberhannes extends Table
{
	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels( array( 
            //    "my_first_global_variable" => 10,
            //    "my_second_global_variable" => 11,
            //      ...
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...
            "trickColor" => 10,
            "playerTookFirstTrick" => 11,
            "playerTookQueenOfClubs" => 12,
            "playerTookLastTrick" => 13,
            "gameLength" => 100
        ) );        
        $this->cards = self::getNew( "module.common.deck" );
        $this->cards->init( "card" );
	}
	
    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "slobberhannes";
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        $start_points = self::getStartingPointCount();
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_score, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$start_points','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( ',', $values );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        //self::setGameStateInitialValue( 'my_first_global_variable', 0 );
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        self::initStat( "table", "handNbr", 0 );
        self::initStat( "player", "getFirstTrick", 0 );
        self::initStat( "player", "getLastTrick", 0 );
        self::initStat( "player", "getQueenOfClubs", 0 );
        self::initStat( "player", "getNoPenalties", 0 );
        self::initStat( "player", "getSlobberhannes", 0 );

        // TODO: setup the initial game situation here
        self::setGameStateInitialValue( 'trickColor', 0 );
        self::setGameStateInitialValue( 'playerTookFirstTrick', 0 );
        self::setGameStateInitialValue( 'playerTookQueenOfClubs', 0 );
        self::setGameStateInitialValue( 'playerTookLastTrick', 0 );

         // Create cards
         $players_nbr = count( $players );
         $cards = array();
         foreach( $this->colors as  $color_id => $color ) // spade, heart, diamond, club
         {
             for( $value=7; $value<=VALUE_ACE; $value++ )   //  7, 8, ... K, A
             {
                if (4 != $players_nbr && 7 == $value && (SUIT_SPADES == $color_id || SUIT_CLUBS == $color_id)) // For 3, 5, or 6 players, exclude 7s of spades and clubs
                {
                    continue;
                }
                 $cards[] = array( 'type' => $color_id, 'type_arg' => $value, 'nbr' => 1);
             }
         }
 
         $this->cards->createCards( $cards, 'deck' );

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );
  
        // TODO: Gather all information about current game situation (visible by player $current_player_id).
         // Cards in player hand      
         $result['hand'] = $this->cards->getCardsInLocation( 'hand', $current_player_id );
  
         // Cards played on the table
         $result['cardsontable'] = $this->cards->getCardsInLocation( 'cardsontable' );
  
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        // TODO: compute and return the game progression
        $maxPoints = self::getStartingPointCount();
        $minimumScore = self::getUniqueValueFromDb( "SELECT MIN( player_score ) FROM player" );

        return 100 * (($maxPoints - $minimumScore) / $maxPoints);
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    // Return players => direction (N/S/E/W) from the point of view
    //  of current player (current player must be on south)
    // 3 players -> 3p_S, 3p_W, 3p_E
    // 4 players -> S, W, N, E
    // 5 players -> 5p_S, 5p_SW, 5p_NW, 5p_NE, 5p_SE
    // 6 players -> 6p_S, 6p_SW, 6p_NW, 6p_N, 6p_NE, 6p_SE
    function getPlayersToDirectionString()
    {
        $result = array();
    
        $players = self::loadPlayersBasicInfos();
        $players_nbr = count( $players );
        $nextPlayer = self::createNextPlayerTable( array_keys( $players ) );

        $current_player = self::getCurrentPlayerId();
        
        if (3 == $players_nbr)
        {
            $directions = array( '3p_S', '3p_W', '3p_E' );
        }
        else if (4 == $players_nbr)
        {
            $directions = array( 'S', 'W', 'N', 'E' );
        }
        else if (5 == $players_nbr)
        {
            $directions = array( '5p_S', '5p_SW', '5p_NW', '5p_NE', '5p_SE' );
        }
        else if (6 == $players_nbr)
        {
            $directions = array( '6p_S', '6p_SW', '6p_NW', '6p_N', '6p_NE', '6p_SE' );
        }
        else
        {
            throw new BgaVisibleSystemException ( self::_("Player count is not supported") );
        }
        
        if( ! isset( $nextPlayer[ $current_player ] ) )
        {
            // Spectator mode: take any player for south
            $player_id = $nextPlayer[0];
            $result[ $player_id ] = array_shift( $directions );
        }
        else
        {
            // Normal mode: current player is on south
            $player_id = $current_player;
            $result[ $player_id ] = array_shift( $directions );
        }
        
        while( count( $directions ) > 0 )
        {
            $player_id = $nextPlayer[ $player_id ];
            $result[ $player_id ] = array_shift( $directions );
        }
        return $result;
    }

    function getStartingPointCount()
    {
		$start_points_value_map = array(0 => 10, 1 => 6, 2 => 10, 3 => 16);
        return $start_points_value_map[self::getGameStateValue( 'gameLength' )];
    }

    function getHandSize()
    {
        $players = self::loadPlayersBasicInfos();
        $players_nbr = count( $players );
        $hand_size_map = array(3 => 10, 4=> 8, 5=> 6, 6 => 5);
        return $hand_size_map[$players_nbr];
    }

    // Hopefully this strat is smart enough to make zombied games not totally unbearable
    // It won't be remotely close to good enough to the point where that would be a problem
    function getZombieCardId()
    {
        $players = self::loadPlayersBasicInfos();
        $players_nbr = count( $players );

        $player_id = self::getActivePlayerId();
        $playerhands = $this->cards->getCardsInLocation( 'hand', $player_id );
        $cardsOnTable = $this->cards->getCardsInLocation('cardsontable');
        // Mr. Zombie is smart enough to remember if the Queen of Clubs has been played.
        // Not planning to give him better memory than that
        $cardsWon = $this->cards->getCardsInLocation('cardswon');

        $bFirstTrick = ( count( $playerhands ) == $this->getHandSize() );
        $bLastTrick = (1 == count($playerhands));
        
        $currentTrickColor = self::getGameStateValue( 'trickColor' ) ;
        $bAmFirstPlayer = (0 == $currentTrickColor);
        $bAmLastPlayer = (count($cardsOnTable) == $players_nbr - 1);

        $bQueenClubIsInTrick = false;
        $bQueenClubIsNotInTrick = false;
        $bQueenClubIsMaybeInTrick = false;

        $bAmPlayingInSuit = false;

        $cardsOnTableInSuit = array();

        $myQueenOfClubs = null;
        $myKingOfClubs = null;
        $myAceOfClubs = null;

        foreach( $cardsWon as $card )
        {
            if( $card['type'] == SUIT_CLUBS && $card['type_arg'] == VALUE_QUEEN )
            {
                $bQueenClubIsNotInTrick = true;
            }
        }
        foreach ($cardsOnTable as $card)
        {
            if ($card['type'] == SUIT_CLUBS && $card['type_arg'] == VALUE_QUEEN)
            {
                $bQueenClubIsInTrick = true;
                $bQueenClubIsMaybeInTrick = true;
            }
            if ($card['type'] == $currentTrickColor)
            {
                $cardsOnTableInSuit[] = $card;
            }
        }
        $highestCardOnTableInSuit = $this->getHighestCard($cardsOnTableInSuit);
        $highestValueOnTableInSuit = 0;
        if (null != $highestCardOnTableInSuit) $highestValueOnTableInSuit = $this->getHighestCard($cardsOnTableInSuit)['type_arg'];
        foreach ($playerhands as $card)
        {
            if ($card['type'] == SUIT_CLUBS && $card['type_arg'] == VALUE_QUEEN)
            {
                $myQueenOfClubs = $card;
                $bQueenClubIsNotInTrick = true; // well, it may be in the trick if we're forced to play it, but that means we have no choice anyways
            }
            if ($card['type'] == SUIT_CLUBS && $card['type_arg'] == VALUE_KING)
            {
                $myKingOfClubs = $card;
            }
            if ($card['type'] == SUIT_CLUBS && $card['type_arg'] == VALUE_ACE)
            {
                $myAceOfClubs = $card;
            }
        }
        if (!$bQueenClubIsNotInTrick && !$bAmLastPlayer)
        {
            $bQueenClubIsMaybeInTrick = true;
        }

        $cardsInSuit = array();
        $cardsOutOfSuit = array();

        foreach ($playerhands as $card)
        {
            if ($card['type'] == $currentTrickColor)
            {
                $bAmPlayingInSuit = true;
                $cardsInSuit[] = $card;
            }
            else
            {
                $cardsOutOfSuit[] = $card;
            }
        }

        // Now that we gathered all that info, actually pick something to play
        // Last trick: turns out we didn't need to think about any of that info
        if ($bLastTrick)
        {
            return array_pop($playerhands)['id'];
        }
        // First trick:
        // 1. If leading, play the lowest card in hand
        // 2. If matching suit, play lowest card in hand
        // 3. If not matching suit, get rid of the best card possible
        if ($bFirstTrick)
        {
            if ($bAmFirstPlayer)
            {
                return $this->getLowestCard($cardsOutOfSuit)['id'];
            }
            else if ($bAmPlayingInSuit)
            {
                if ($bAmLastPlayer)
                {
                    $highestSafeCard = $this->getHighestCardBelowValue($cardsInSuit, $highestValueOnTableInSuit);
                    if (null != $highestSafeCard) return $highestSafeCard['id'];
                    // If we're last and can't be safe, might as well get rid of the highest card altogether
                    return $this->getHighestCard($cardsInSuit)['id'];
                }
                else
                {
                    return $this->getLowestCard($cardsInSuit)['id'];
                }
            }
            else
            {
                return $this->getPrioritySlough($cardsOutOfSuit)['id'];
            }
        }
        // Normal trick:
        // 1. If we're leading, play the priority lead
        // 2. If we're out of suit, play the priority slough
        // 3. If we're in suit and the suit is clubs:
            // a. If Queen could possibly be played, play our highest card below it (if possible), else play highest
            // b. If Queen cannot possibly be played by someone else, play our highest
        // 4. If we're in suit for a different suit:
            // a. If Queen could possibly be played, always be a wimp and play the lowest card we have in suit
            // b. If Queen cannot possible be played, play our highest
        if ($bAmFirstPlayer)
        {
            return $this->getPriorityLead($cardsOutOfSuit)['id'];
        }
        if (!$bAmPlayingInSuit)
        {
            return $this->getPrioritySlough($cardsOutOfSuit)['id'];
        }
        // We know for sure whether Queen of Clubs is in the trick, as well as which cards will take the trick
        // Thus, if we can't dodge the trick, we can take the trick with as high of a card as we have
        if ($bAmLastPlayer)
        {
            if ($bQueenClubIsNotInTrick)
            {
                return $this->getHighestCard($cardsInSuit)['id'];
            }
            else
            {
                $highestSafeCard = $this->getHighestCardBelowValue($cardsInSuit, $highestValueOnTableInSuit);
                if (null != $highestSafeCard) return $highestSafeCard['id'];
                return $this->getHighestCard($cardsInSuit)['id'];
            }
        }
        // Otherwise, we'll try our hardest to dodge if we're unable to guarantee something low
        if (SUIT_CLUBS == $currentTrickColor)
        {
            if ($bQueenClubIsNotInTrick) // In this case, we know the Queen of Clubs can't show up anyways, so just take it
            {
                return $this->getHighestCard($cardsInSuit)['id'];
            }
            else
            {
                // Drop the queen if we can
                if ($highestValueOnTableInSuit > VALUE_QUEEN && $myQueenOfClubs != null) return $myQueenOfClubs['id'];
                // Barring that, try to be safe
                $highestSafeCard = $this->getHighestCardBelowValue($cardsInSuit, $highestValueOnTableInSuit);
                if (null != $highestSafeCard) return $highestSafeCard['id'];
                // Clubs is a special case because it means we can never take Queen of Clubs with a card below the Queen
                // Therefore, we don't need to worry about taking the trick in general
                /// ...but we still checked the table first, in case somebody already played the Ace and we didn't have to boter
                $belowQueen = $this->getHighestCardBelowValue($cardsInSuit, VALUE_QUEEN);
                if (null != $belowQueen) return $belowQueen['id'];
                
                // Even if we can't play a club below the queen, priority order is K > A > Q
                if ($myKingOfClubs != null) return $myKingOfClubs['id'];
                if ($myAceOfClubs != null) return $myAceOfClubs['id'];
                // We have a card in suit, none that are below Queen, and not the King or Ace... take one guess what this card is
                // Still leaving a function call as the final catch case just in case the logic gets adjusted later and breaks that
                return $this->getHighestCard($cardsInSuit)['id'];
            }
        }
        else
        {
            if ($bQueenClubIsNotInTrick)
            {
                return $this->getHighestCard($cardsInSuit)['id'];
            }
            else
            {
                $highestSafeCard = $this->getHighestCardBelowValue($cardsInSuit, $highestValueOnTableInSuit);
                if (null != $highestSafeCard) return $highestSafeCard['id'];
                return $this->getLowestCard($cardsInSuit)['id'];
            }
        }
    }

    function getHighestCardBelowValue($cards, $value)
    {
        $highestCard = null;
        foreach ($cards as $card)
        {
            if ($card['type_arg'] < $value && (null == $highestCard || $card['type_arg'] > $highestCard['type_arg']))
            {
                $highestCard = $card;
            }
        }
        return $highestCard;
    }

    function getLowestCard($cards)
    {
        $lowestCard = null;
        foreach ($cards as $card)
        {
            if (null == $lowestCard || $card['type_arg'] < $lowestCard['type_arg'])
            {
                $lowestCard = $card;
            }
        }
        return $lowestCard;
    }

    function getHighestCard($cards)
    {
        $highestCard = null;
        foreach ($cards as $card)
        {
            if (null == $highestCard || $card['type_arg'] > $highestCard['type_arg'])
            {
                $highestCard = $card;
            }
        }
        return $highestCard;
    }

    function getPriorityLead($cards)
    {
        // 1. Play highest club to bait, if we don't have Q/K/A of clubs
        // 2. Void a suit if we can do so immediately
        // 3. Play the lowest card we have (Mr. Zombie is a wimp)
        $bHasClubsQka = false;
        $highestClub = null;
        $nonClubsQkaCards = array();
        foreach ($cards as $card)
        {
            if ($card['type'] == SUIT_CLUBS)
            {
                if ($card['type_arg']  == VALUE_QUEEN || $card['type_arg'] == VALUE_KING || $card['type_arg'] == VALUE_ACE)
                {
                    $bHasClubsQka = true;
                }
                else
                {
                    $nonClubsQkaCards[] = $card;
                }
                if (null == $highestClub || $card['type_arg'] > $highestClub['type_arg'])
                {
                    $highestClub = $card;
                }
            }
            else
            {
                $nonClubsQkaCards[] = $card;
            }
        }
        if (!$bHasClubsQka && null != $highestClub)
        {
            return $highestClub;
        }
        $voidingCard = $this->getHighestImmediatelyVoidingCardIfOneExists($nonClubsQkaCards);
        if (null != $voidingCard) return $voidingCard;
        return $this->getLowestCard($cards);
    }

    function getHighestImmediatelyVoidingCardIfOneExists($cards)
    {
        $cardCounts = array(SUIT_CLUBS => 0, SUIT_HEARTS => 0, SUIT_DIAMONDS => 0, SUIT_SPADES => 0);
        $singleCardIfIsSingle = array(SUIT_CLUBS => null, SUIT_HEARTS => null, SUIT_DIAMONDS => null, SUIT_SPADES => null);
        foreach ($cards as $card)
        {
            $cardCounts[$card['type']]++;
            $singleCardIfIsSingle[$card['type']] = $card;
        }
        $cardsThatWouldVoidSuit = array();
        foreach ($cardCounts as $suitValue => $suitCount)
        {
            if (1 == $suitCount)
            {
                $cardsThatWouldVoidSuit[] = $singleCardIfIsSingle[$suitValue];
            }
        }
        if (count($cardsThatWouldVoidSuit) > 0)
        {
            return $this->getHighestCard($cardsThatWouldVoidSuit);
        }
        return null;
    }

    function getPrioritySlough($cards)
    {
        $queenOfClubs = null;
        $aceOfClubs = null;
        $kingOfClubs = null;
        $cardCounts = array(SUIT_CLUBS => 0, SUIT_HEARTS => 0, SUIT_DIAMONDS => 0, SUIT_SPADES => 0);
        //$singleCardIfIsSingle = array(SUIT_CLUBS => null, SUIT_HEARTS => null, SUIT_DIAMONDS => null, SUIT_SPADES => null);
        $highestCardValue = 0;
        // 1. Drop Queen of Clubs on someone
        // 2. Get rid of King or Ace of Clubs
        // 3. Get rid of last card in any suit we only have 1 of (highest of these as tiebreaker)
        // 4. Get rid of highest card (shortest-suited of these as tiebreaker)
        foreach ($cards as $card)
        {
            $cardCounts[$card['type']]++;
            //$singleCardIfIsSingle[$card['type']] = $card;
            if ($card['type'] == SUIT_CLUBS)
            {
                if ($card['type_arg']  == VALUE_QUEEN)
                {
                    $queenOfClubs = $card;
                }
                else if ($card['type_arg'] == VALUE_KING)
                {
                    $kingOfClubs = $card;
                }
                else if ($card['type_arg'] == VALUE_ACE)
                {
                    $aceOfClubs = $card;
                }
            }
            if ($card['type_arg'] > $highestCardValue)
            {
                $highestCardValue = $card['type_arg'];
            }
        }
        
        if (null != $queenOfClubs) return $queenOfClubs;
        if (null != $aceOfClubs) return $aceOfClubs;
        if (null != $kingOfClubs) return $kingOfClubs;
        /*$cardsThatWouldVoidSuit = array();
        foreach ($cardCounts as $suitValue => $suitCount)
        {
            if (1 == $suitCount)
            {
                $cardsThatWouldVoidSuit[] = $singleCardIfIsSingle[$suitValue];
            }
        }
        if (count($cardsThatWouldVoidSuit) > 0)
        {
            return $this->getHighestCard($cardsThatWouldVoidSuit);
        }*/
        $voidingCard = $this->getHighestImmediatelyVoidingCardIfOneExists($cards);
        if (null != $voidingCard) 
        {
            return $voidingCard;
        }

        $shortestCardWithHighestValue = null;
        $currentLowestSuitCount = 1000;
        foreach ($cards as $card)
        {
            if ($card['type_arg'] == $highestCardValue)
            {
                if ($cardCounts[$card['type']] < $currentLowestSuitCount)
                {
                    $shortestCardWithHighestValue = $card;
                    $currentLowestSuitCount = $cardCounts[$card['type']];
                }
            }
        }
        return $shortestCardWithHighestValue;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in slobberhannes.action.php)
    */

    /*
    
    Example:

    function playCard( $card_id )
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'playCard' ); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card there 
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays ${card_name}' ), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ) );
          
    }
    
    */

        // Play a card from player hand
        function playCard( $card_id )
        {
            self::checkAction( "playCard" );
            
            $player_id = self::getActivePlayerId();
            
            // Get all cards in player hand
            // (note: we must get ALL cards in player's hand in order to check if the card played is correct)
            
            $playerhands = $this->cards->getCardsInLocation( 'hand', $player_id );
    
            $bFirstCard = ( count( $playerhands ) == $this->getHandSize() );
                    
            $currentTrickColor = self::getGameStateValue( 'trickColor' ) ;
                    
            // Check that the card is in this hand
            $bIsInHand = false;
            $currentCard = null;
            $bAtLeastOneCardOfCurrentTrickColor = false;

            foreach( $playerhands as $card )
            {
                if( $card['id'] == $card_id )
                {
                    $bIsInHand = true;
                    $currentCard = $card;
                }
                
                if( $card['type'] == $currentTrickColor )
                {
                    $bAtLeastOneCardOfCurrentTrickColor = true;
                }
            }
            if( ! $bIsInHand )
            {
                throw new BgaUserException( "This card is not in your hand" );
            }
                
            if( $currentTrickColor != 0
                && $bAtLeastOneCardOfCurrentTrickColor 
                && $currentCard['type'] != $currentTrickColor)
            {
                throw new BgaUserException ( sprintf( self::_("You must play a %s"), $this->colors[ $currentTrickColor ]['nametr'] ), true );
            }
            
            // Checks are done! now we can play our card
            $this->cards->moveCard( $card_id, 'cardsontable', $player_id );
            
            // Set the trick color if it hasn't been set yet
            if( $currentTrickColor == 0 )
                self::setGameStateValue( 'trickColor', $currentCard['type'] );
            
            /*if( $currentCard['type'] == 2 )
                self::setGameStateValue( 'alreadyPlayedHearts', 1 );*/
            
            // And notify
            self::notifyAllPlayers( 'playCard', clienttranslate('${player_name} plays ${value_displayed} ${color_displayed}'), array(
                'i18n' => array( 'color_displayed', 'value_displayed' ),
                'card_id' => $card_id,
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'value' => $currentCard['type_arg'],
                'value_displayed' => $this->values_label[ $currentCard['type_arg'] ],
                'color' => $currentCard['type'],
                'color_displayed' => $this->colors[ $currentCard['type'] ]['name']
            ) );
            
            // Next player
            $this->gamestate->nextState( 'playCard' );
        }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */
    
    /*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }    
    */
    function stNewHand()
    {
        self::incStat( 1, "handNbr" );
        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation( null, "deck" );
        $this->cards->shuffle( 'deck' );
    
        // Deal cards to each players
        // Create deck, shuffle it and give 13 initial cards
        $players = self::loadPlayersBasicInfos();
        foreach( $players as $player_id => $player )
        {
            $cards = $this->cards->pickCards( $this->getHandSize(), 'deck', $player_id );
            
            // Notify player about his cards
            self::notifyPlayer( $player_id, 'newHand', '', array( 
                'cards' => $cards
            ) );
        }        
        

        $this->gamestate->nextState( "" );
    }
	
	function stNewTrick()
	{
        self::setGameStateValue( 'trickColor', 0 );
		$this->gamestate->nextState( );
	}
	
	function stNextPlayer()
	{
		$players = self::loadPlayersBasicInfos();
        $players_nbr = count($players);
        if( $this->cards->countCardInLocation( 'cardsontable' ) == $players_nbr ) // everyone has played
        {
            $bIsFirstTrick = ($this->cards->countCardInLocation( 'cardswon' ) == 0);
            $bIsLastTrick = ($this->cards->countCardInLocation( 'hand' ) == 0);
            $bContainsQueenOfClubs = 0;
            // This is the end of the trick
            // Who wins ?
            
            $cards_on_table = $this->cards->getCardsInLocation( 'cardsontable' );
            $best_value = 0;
            $best_value_player_id = null;
            $currentTrickColor = self::getGameStateValue( 'trickColor' );
            
            foreach( $cards_on_table as $card )
            {
                if ( $card['type'] == SUIT_CLUBS && $card['type_arg'] == VALUE_QUEEN)
                {
                    $bContainsQueenOfClubs = 1;
                }
                if( $card['type'] == $currentTrickColor )   // Note: type = card color
                {
                    if( $best_value_player_id === null )
                    {
                        $best_value_player_id = $card['location_arg'];  // Note: location_arg = player who played this card on table
                        $best_value = $card['type_arg'];        // Note: type_arg = value of the card
                    }
                    else if( $card['type_arg'] > $best_value )
                    {
                        $best_value_player_id = $card['location_arg'];  // Note: location_arg = player who played this card on table
                        $best_value = $card['type_arg'];        // Note: type_arg = value of the card
                    }
                }
            }
            
            if( $best_value_player_id === null )
                throw new  BgaVisibleSystemException ( self::_("Error, nobody wins the trick") );
            
            
            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation( 'cardsontable', 'cardswon', null, $best_value_player_id );

            if ($bIsFirstTrick) self::setGameStateValue('playerTookFirstTrick', $best_value_player_id);
            if ($bIsLastTrick) self::setGameStateValue('playerTookLastTrick', $best_value_player_id);
            if ($bContainsQueenOfClubs) self::setGameStateValue('playerTookQueenOfClubs', $best_value_player_id);

            // Notify
            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers( 'trickWin', clienttranslate('${player_name} wins the trick'), array(
                'player_id' => $best_value_player_id,
                'player_name' => $players[ $best_value_player_id ]['player_name']
            ) );            
            self::notifyAllPlayers( 'giveAllCardsToPlayer','', array(
                'player_id' => $best_value_player_id
            ) );

            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer( $best_value_player_id );
            
            if( $bIsLastTrick )
            {
                // End of the hand
                $this->gamestate->nextState( "endHand" );
            }
            else
            {
                // End of the trick
                $this->gamestate->nextState( "nextTrick" );
            }
        }
        else
        {

            $player_id = $this->activeNextPlayer();
            self::giveExtraTime( $player_id );
            //$this->gamestate->changeActivePlayer( $player_id );

            $this->gamestate->nextState( 'nextPlayer' );        
        }
	}
	
	function stEndHand()
	{
		// Count and score points, then end the game or go to the next hand.
                
        $players = self::loadPlayersBasicInfos();
        
        $player_to_points = array();
        foreach( $players as $player_id => $player )
        {
            $playerPenaltyPoints = 0;
            if ($player_id == self::getGameStateValue('playerTookFirstTrick'))
            {
                self::incStat( 1, "getFirstTrick", $player_id );
                self::notifyAllPlayers( "penalty", clienttranslate( '${player_name} took the first trick penalty and loses 1 point' ), array(
                    'player_id' => $player_id,
                    'player_name' => $players[ $player_id ]['player_name'],
                ) );
                $playerPenaltyPoints--;
            }
            if ($player_id == self::getGameStateValue('playerTookLastTrick'))
            {
                self::incStat( 1, "getLastTrick", $player_id );
                self::notifyAllPlayers( "penalty", clienttranslate( '${player_name} took the last trick penalty and loses 1 point' ), array(
                    'player_id' => $player_id,
                    'player_name' => $players[ $player_id ]['player_name'],
                ) );
                $playerPenaltyPoints--;
            }
            if ($player_id == self::getGameStateValue('playerTookQueenOfClubs'))
            {
                self::incStat( 1, "getQueenOfClubs", $player_id );
                self::notifyAllPlayers( "penalty", clienttranslate( '${player_name} took the Queen of Clubs and loses 1 point' ), array(
                    'player_id' => $player_id,
                    'player_name' => $players[ $player_id ]['player_name'],
                ) );
                $playerPenaltyPoints--;
            } 
            if (-3 == $playerPenaltyPoints)
            {
                // Slobberhannes penalty
                self::incStat( 1, "getSlobberhannes", $player_id );
                self::notifyAllPlayers( "penalty", clienttranslate( '${player_name} takes all three penalties, incurring the Slobberhannes penalty! ${player_name} loses an additional 1 point' ), array(
                    'player_id' => $player_id,
                    'player_name' => $players[ $player_id ]['player_name'],
                ) );
                $playerPenaltyPoints--;
            }
            if (0 == $playerPenaltyPoints)
            {
                self::incStat( 1, "getNoPenalties", $player_id );
                self::notifyAllPlayers( "points", clienttranslate( '${player_name} did not take any penalties' ), array(
                    'player_id' => $player_id,
                    'player_name' => $players[ $player_id ]['player_name']
                ) );
            }
            $player_to_points[$player_id] = $playerPenaltyPoints;
        }

        // Apply scores to player
        foreach( $player_to_points as $player_id => $points )
        {
            if( $points != 0 )
            {
                $sql = "UPDATE player SET player_score=player_score+$points
                        WHERE player_id='$player_id' " ;
                self::DbQuery( $sql );
            }
        }

        $newScores = self::getCollectionFromDb( "SELECT player_id, player_score FROM player", true );
        self::notifyAllPlayers( "newScores", '', array( 'newScores' => $newScores ) );
        
        //////////// Display table window with results /////////////////
        $table = array();

        // Header line
        $firstRow = array( '' );
        foreach( $players as $player_id => $player )
        {
            $firstRow[] = array( 'str' => '${player_name}',
                                 'args' => array( 'player_name' => $player['player_name'] ),
                                 'type' => 'header'
                               );
        }
        $table[] = $firstRow;

        // First trick
        $newRow = array( array( 'str' => clienttranslate('First Trick'), 'args' => array() ) );
        foreach( $player_to_points as $player_id => $points )
        {
            if ($player_id == self::getGameStateValue('playerTookFirstTrick'))
            {
                $newRow[] = clienttranslate('Yes');
            }
            else
            {
                $newRow[] = '-';
            }
        }
        $table[] = $newRow;

        // Last trick
        $newRow = array( array( 'str' => clienttranslate('Last Trick'), 'args' => array() ) );
        foreach( $player_to_points as $player_id => $points )
        {
            if ($player_id == self::getGameStateValue('playerTookLastTrick'))
            {
                $newRow[] = clienttranslate('Yes');
            }
            else
            {
                $newRow[] = '-';
            }
        }
        $table[] = $newRow;

        // Queen of Clubs
        $newRow = array( array( 'str' => clienttranslate('Queen of Clubs'), 'args' => array() ) );
        foreach( $player_to_points as $player_id => $points )
        {
            if ($player_id == self::getGameStateValue('playerTookQueenOfClubs'))
            {
                $newRow[] = clienttranslate('Yes');
            }
            else
            {
                $newRow[] = '-';
            }
        }
        $table[] = $newRow;

        // Slobberhannes
        $newRow = array( array( 'str' => clienttranslate('Slobberhannes'), 'args' => array() ) );
        foreach( $player_to_points as $player_id => $points )
        {
            if ($points == -4)
            {
                $newRow[] = clienttranslate('Yes');
            }
            else
            {
                $newRow[] = '-';
            }
        }
        $table[] = $newRow;
        
        $this->notifyAllPlayers( "tableWindow", '', array(
            "id" => 'finalScoring',
            "title" => clienttranslate("Result of this hand"),
            "table" => $table,
            "closing" => clienttranslate ("OK")
        ) ); 
        
        ///// Test if this is the end of the game
        foreach( $newScores as $player_id => $score )
        {
            if( $score <= 0 )
            {
                // Trigger the end of the game !
                $this->gamestate->nextState( "endGame" );
                return ;
            }
        }

        // Otherwise... new hand !
        $this->gamestate->nextState( "nextHand" );
	}
	

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                case 'playerTurn':
                    $this->playCard($this->getZombieCardId());
                    break;
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
