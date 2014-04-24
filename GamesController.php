<?php

class SuperAdmin_GamesController extends MrBlue_Controller_Action_SuperAdmin
{
    public function init()
    {
        parent::init();
        
        // set menu item as active
        $this->view->navigation()->findOneByLabel('Games')->setActive();
    }
    
    public function indexAction()
    {
        $this->view->headTitle('Available Games');

        $this->oSuperAdminNS->sGameRedirectUrl = Zend_Controller_Front::getInstance()->getRequest()->getRequestUri();
        
        $oGamesApp = new Model_GamesApp();
        $aGames = $oGamesApp->getList(-1, null, null, 0, false);
        $this->view->aGames = $aGames;
    }
    
    public function finishedAction()
    {
        $this->view->headTitle('Correct answers needed');

        $this->oSuperAdminNS->sGameRedirectUrl = Zend_Controller_Front::getInstance()->getRequest()->getRequestUri();
        
        $oGamesApp = new Model_GamesApp();
        $aGames = $oGamesApp->getList(-1, null, null, 0, true);
        $this->view->aGames = $aGames;
    }
    
    public function createAction()
    {
        $this->view->headTitle('Create new game');
        
        $oGameForm = new SuperAdmin_Form_Games_GameForm();
        // is POST request?
        if ( !empty($this->aPost) ) {
            // form is valid?
            if ($oGameForm->isValid($this->aPost)) {
                $oGamesApp = new Model_GamesApp();
                $id = $oGamesApp->add( array_merge($oGameForm->getValues(),array('visible'=>0)) );
                
                if( is_int($id) ) {
                    return $this->redirect('superadmin/games/game-setup/id/'.$id);
                } else {
                    $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
                }
            }
        }
        $this->view->gameForm = $oGameForm;
    }
    
    public function changeVisibleAction()
    {
        if( !isset($this->params['id']) || !is_numeric($this->params['id']) ) {
            return $this->_helper->redirector('index');
        }
        
        $this->view->headTitle('Set the game visible for players?');
        
        $oGamesApp = new Model_GamesApp(intval($this->params['id']));
        
        // getting saved questions & answers
        $aQuestionsAnswers = $oGamesApp->getGame();
        
        if( empty($aQuestionsAnswers['questions']) ) {
            $this->_flashMessenger->addMessage(array('error', 'You cannot change a visibility of a game without questions'));
            return $this->redirect('superadmin/games/game-setup/id/'.intval($this->params['id']));
        }
        
        if( isset($this->params['token']) ) {
            if( $this->params['token']==$this->oSuperAdminNS->sGameVisibleToken ) {
                $bCanChange = $oGamesApp->canChangeStatus();
                if( $bCanChange ) {
                    // changing the visibility
                    $bChanged = $oGamesApp->changeStatus();
                    if($bChanged) {
                        unset($this->oSuperAdminNS->sGameVisibleToken);
                        $this->_flashMessenger->addMessage(array('success', 'Done! Great!'));
                        return $this->_helper->redirector('index');
                    } else {
                        $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
                    }
                } else {
                    $this->_flashMessenger->addMessage(array('error', 'Sorry, but some users are playing in this game.'));
                }
            }
            return $this->redirect('superadmin/games/change-visible/id/'.intval($this->params['id']));
        }
        
        $aGame = $oGamesApp->getDbTable()->toArray();
        $this->view->aGame = $aGame;
        
        $this->oSuperAdminNS->sGameVisibleToken = md5(time().time().MrBlue_Helper_Project::queCode());
        $this->view->sGameVisibleToken = $this->oSuperAdminNS->sGameVisibleToken;
    }
    
    public function gameSetupAction()
    {
        if( !isset($this->params['id']) || !is_numeric($this->params['id']) ) {
            return $this->_helper->redirector('index');
        }
        
        $this->view->headTitle('Game - set questions and answers');
        
        $oGamesApp = new Model_GamesApp(intval($this->params['id']));
        $aGame = $oGamesApp->getDbTable()->toArray();
        $this->view->aGame = $aGame;
        
        // questions
        $aQuestions = array();
        $aAnswers = array();
        $aAnswersCorrect = array();
        $aCanceledQuestions = array();
        
        if( !empty($this->aPost) )
        {
            if( isset($this->aPost['cancel_game']) ) {
                $this->redirect('superadmin/games/game-cancel/id/'.$this->params['id']);
            }
            
            if( isset($this->aPost['cancel_question']) && is_numeric($this->aPost['cancel_question']) ) {
                $this->redirect('superadmin/games/question-cancel/id/'.$this->aPost['cancel_question']);
            }
            
            $aQuestions = isset($this->aPost['question']) ? $this->aPost['question'] : array() ;
            $aAnswers = isset($this->aPost['qu_answer']) ? $this->aPost['qu_answer'] : array() ;
            $aNewAnswers = isset($this->aPost['new_qu_answer']) ? $this->aPost['new_qu_answer'] : array() ;
            $aAnswersCorrect = isset($this->aPost['qu_answer_correct']) ? $this->aPost['qu_answer_correct'] : array() ;
            
            if( $aGame['game_end']>MrBlue_Helper_System::getDateTime() )
            {
                // updating basic game info (name and dates)
                $oGamesApp->saveBasicInfo(null,array(
                                    'game_name' => $this->aPost['game_name'],
                                    'game_start' => $this->aPost['game_start'],
                                    'game_end' => $this->aPost['game_end'],
                                    'visible' => isset($this->aPost['visible']) ? 1 : $aGame['visible'],
                            ));
                
                // adding/setting the questions to db
                if( !empty($aQuestions) ) {
                    $bAdded = $oGamesApp->setupQuestions(intval($this->params['id']),$aQuestions,$aAnswers,$aNewAnswers);
                    if( $bAdded===true ) {
                        $this->_flashMessenger->addMessage(array('success', 'Done! Great!'));
                        return $this->redirect($this->getRedirectUrl());
                    } else {
                        $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
                    }
                }
            }
            else {
                // only marking an active answer are available!
                //die_dump( $aAnswersCorrect );
                if( !empty($aAnswersCorrect) )
                {
                    if( count($aAnswersCorrect)!=count($aQuestions) ) {
                        $this->_flashMessenger->addMessage(array('error', 'Please set correct answers for all questions.'));
                        $this->redirect('superadmin/games/game-setup/id/'.intval($this->params['id']));
                    }
                    
                    $bAdded = $oGamesApp->markCorrectAnswers(intval($this->params['id']),$aAnswersCorrect);
                    if( $bAdded===true ) {
                        $this->_flashMessenger->addMessage(array('success', 'Done! Great!'));
                        return $this->redirect($this->getRedirectUrl());
                    } elseif( is_string($bAdded) ) {
                        $this->_flashMessenger->addMessage(array('error', $bAdded));
                        $this->redirect('superadmin/games/game-setup/id/'.intval($this->params['id']));
                    } else {
                        $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
                    }
                }
            }
        } else {
            // getting saved questions & answers
            $aQuestionsAnswers = $oGamesApp->getGame();
            
            if( empty($aQuestionsAnswers['questions']) ) {
                $aQuestions[Model_GamesApp::DEFAULT_QUESTION_ID] = '';
                $aAnswers[Model_GamesApp::DEFAULT_QUESTION_ID] = array(-1=>'',-2=>'');
            } else {
                $aQuestions = $aQuestionsAnswers['questions'];
                $aAnswers = $aQuestionsAnswers['answers'];
                $aAnswersCorrect = $aQuestionsAnswers['correct'];
                $aCanceledQuestions = $aQuestionsAnswers['canceled'];
            }
        }
        ksort($aQuestions);
        ksort($aAnswers);
        ksort($aAnswersCorrect);
        $this->view->aQuestions = $aQuestions;
        $this->view->aAnswers = $aAnswers;
        $this->view->aAnswersCorrect = $aAnswersCorrect;
        $this->view->aCanceledQuestions = $aCanceledQuestions;
        
        $aQuestionKeys = array_keys($aQuestions);
        $iNewQuestionID = end($aQuestionKeys);
        if( $iNewQuestionID<Model_GamesApp::DEFAULT_QUESTION_ID ) {
            $iNewQuestionID = Model_GamesApp::DEFAULT_QUESTION_ID;
        }
        $this->view->iNewQuestionID = $iNewQuestionID;
        
        $bShowCorrectAnswerRadio = false;
        if( $aGame['game_end']<=MrBlue_Helper_System::getDateTime() ) {
            $bShowCorrectAnswerRadio = true;
        }
        $this->view->bShowCorrectAnswerRadio = $bShowCorrectAnswerRadio;
        
    }

    private function getRedirectUrl()
    {
        $sRedirectUrl = $this->oSuperAdminNS->sGameRedirectUrl;

        if( is_null($sRedirectUrl) || empty($sRedirectUrl) ) {
            $sRedirectUrl = '/superadmin/games';
        }
        return $sRedirectUrl;
    }
    
    
    public function gameCancelAction()
    {
        if( !isset($this->params['id']) || !is_numeric($this->params['id']) ) {
            return $this->_helper->redirector('index');
        }
        
        $this->view->headTitle('Cancel the game?');
        
        $oGamesApp = new Model_GamesApp(intval($this->params['id']));
        
        if( !$oGamesApp->bLoaded || $oGamesApp->getDbTable()->game_canceled==1 )
        {
            $this->_flashMessenger->addMessage(array('error', 'Oopps... you cannot cancel that game!'));
            return $this->_helper->redirector('index');
        }
        
        $oCancelForm = new SuperAdmin_Form_Games_CancelGameForm();
        
        if( !empty($this->aPost) && $oCancelForm->isValid($this->aPost) )
        {
            $bCanceled = $oGamesApp->cancelGame($oCancelForm->getValue('cancel_reason'));
            
            if($bCanceled) {
                $this->_flashMessenger->addMessage(array('success', 'Done! Great!'));
                return $this->_helper->redirector('index');
            } else {
                $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
            }
        }
        
        $this->view->oCancelForm = $oCancelForm;
    }
    
    
    public function questionCancelAction()
    {
        if( !isset($this->params['id']) || !is_numeric($this->params['id']) ) {
            return $this->_helper->redirector('index');
        }
        
        $oQuestionsApp = new Model_GamesQuestionsApp(intval($this->params['id']));
        
        if( !$oQuestionsApp->bLoaded )
        {
            $this->_flashMessenger->addMessage(array('error', 'Oopps... the question doesnt exists? '));
            return $this->_helper->redirector('index');
        }
        
        if( $oQuestionsApp->getDbTable()->question_canceled==1 ) {
            $this->_flashMessenger->addMessage(array('error', 'there is no reason to mark this question canceled again, isnt it'));
            return $this->_helper->redirector('index');
        }
        
        $oCancelForm = new SuperAdmin_Form_Games_CancelQuestionForm();
        
        if( !empty($this->aPost) && $oCancelForm->isValid($this->aPost) )
        {
            $bCanceled = $oQuestionsApp->cancelQuestion($oCancelForm->getValue('cancel_reason'));
            
            if($bCanceled) {
                $this->_flashMessenger->addMessage(array('success', 'Done! Great!'));
                return $this->_helper->redirector('index');
            } else {
                $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
            }
        }
        
        $this->view->oCancelForm = $oCancelForm;
    }
    
    
    public function canceledAction()
    {
        $this->view->headTitle('List of canceled games');

        $oGamesApp = new Model_GamesApp();
        $aGames = $oGamesApp->getListCanceled();
        $this->view->aGames = $aGames;
    }
    
    public function completedAction()
    {
        $this->view->headTitle('List of completed games');

        $oGamesApp = new Model_GamesApp();
        $aGames = $oGamesApp->getList(-1, null, null, 1);
        $this->view->aGames = $aGames;
    }
    
    
    public function categoriesAction()
    {
        $this->view->headTitle('Games categories');
        
        $oGameCategoriesApp = new Model_GamesCategoriesApp();
        
        if( isset($this->params['change-status']) && is_numeric($this->params['change-status']) ) {
            $bChanged = $oGameCategoriesApp->changeStatus(intval($this->params['change-status']));
            if($bChanged) {
                $this->_flashMessenger->addMessage(array('success', 'Changed, thanks.'));
            } else {
                $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
            }
            return $this->_helper->redirector('categories');
        }
        
        $oGameCategoryForm = new SuperAdmin_Form_Games_CategoryForm();
        // is POST request?
        if ( !empty($this->aPost) ) {
            // form is valid?
            if ($oGameCategoryForm->isValid($this->aPost)) {
                $bAdded = $oGameCategoriesApp->add($oGameCategoryForm->getValues());
                
                if( $bAdded ) {
                    $this->_flashMessenger->addMessage(array('success', 'Wow, added! :)'));
                } else {
                    $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
                }
                return $this->_helper->redirector('categories');
            }
        }
        $this->view->gameCategoryForm = $oGameCategoryForm;
        
        $aCategories = $oGameCategoriesApp->getList();
        $this->view->aCategories = $aCategories;
    }
    
    
    public function categoryEditAction()
    {
        if( !isset($this->params['id']) || !is_numeric($this->params['id']) ) {
            $this->_flashMessenger->addMessage(array('error', 'Hey... com\'n - you must specify the ID !'));
            return $this->_helper->redirector('categories');
        }
        
        $oGameCategoriesApp = new Model_GamesCategoriesApp();
        $oGameCategoriesApp->loadModel(intval($this->params['id']));
        
        $this->view->headTitle('Edit category - '.$oGameCategoriesApp->getDbTable()->name);
        
        $oGameCategoryForm = new SuperAdmin_Form_Games_CategoryForm();
        // is POST request?
        if ( !empty($this->aPost) ) {
            // form is valid?
            if ($oGameCategoryForm->isValid($this->aPost)) {
                $bChanged = $oGameCategoriesApp->save($oGameCategoryForm->getValues());
                
                if($bChanged) {
                    $this->_flashMessenger->addMessage(array('success', 'Changed, thanks.'));
                } else {
                    $this->_flashMessenger->addMessage(array('error', 'Oopps... something went wrong!'));
                }
                return $this->_helper->redirector('categories');
            }
        } else {
            $oGameCategoryForm->populate($oGameCategoriesApp->getDbTable()->toArray());
        }
        $this->view->gameCategoryForm = $oGameCategoryForm;
    }
    
}
