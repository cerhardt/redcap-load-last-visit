<?php
namespace HIH\LoadLastVisitData;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

use \REDCap as REDCap;
use \Records as Records;
use \DateTimeRC as DateTimeRC;

class LoadLastVisitData extends AbstractExternalModule {

    function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
        $bPrefilled = $this->loadData($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance);
        if ($bPrefilled) {
            $this->createPassthruForm($project_id,$record,$instrument, $event_id);
        }
    }
    
    function hook_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
        $this->loadData($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance);
    }
    
    private function loadData($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {

        if (isset($project_id)) {
            
            global $Proj, $lang, $user_rights;

            if (empty($record)) {
                $record = $_GET['id'];
            }
            if (empty($record)) {
                return false;
            }               

            $all_rights = REDCap::getUserRights();
            $rights = $all_rights[$user_rights['username']];
            // load data only if user has edit permission for instrument / survey
            if (!defined('SUPER_USER') || !SUPER_USER) {
                if ($rights['forms'][$instrument] != '1' && $rights['forms'][$instrument] != '3') {
                    return false;
                }
            }

            // load module configuration
            $aModConfigRaw = $this->getProjectSettings($project_id);
            $aModConfig = array();
            foreach($aModConfigRaw as $sKey => $aTmp) {
                $aModConfig[$sKey] = $aTmp['value'];
            }

            if ($Proj->longitudinal) {
                $val_type = $Proj->metadata[$aModConfig['visit_date']]['element_validation_type'];
                if (substr($val_type, 0, 4) !== 'date') return false;
            }

            // backward compatibility < v2
            $aFormsToPreload = $aModConfig['form'];
            $bNewConfig = true;
            $bLoadAllPreviousEvents = true;
            $bUpdateInstrument = false;
            $aCurrentInstrument = array();
            
            if ((!isset($aModConfig['form'][0]) || strlen($aModConfig['form'][0]) == 0) && isset($aModConfig['forms'][0])) {
                $aFormsToPreload = $aModConfig['forms'];
                $bNewConfig = false;
            }

            if (is_array($aFormsToPreload) && in_array($instrument,$aFormsToPreload)) {
    		
                // get settings for instrument
                if ($bNewConfig) {
                    foreach($aFormsToPreload as $iFormIdx => $sForm) {
                        if ($sForm == $instrument) {
                            if (strlen($aModConfig['form_logic'][$iFormIdx]) > 0) {
                                $bLogic = true;
                                $sLogic = $aModConfig['form_logic'][$iFormIdx];
                            } else {
                                $bLogic = false;
                                if (strlen($aModConfig['load_status_form'][$iFormIdx][0]) > 0) {
                                    $aLoadStatus = $aModConfig['load_status_form'][$iFormIdx];
                                } elseif (isset($aModConfig['load_status'])) {
                                    $aLoadStatus = $aModConfig['load_status'];
                                }
                            }
                            if (strlen($aModConfig['load_all_events_form'][$iFormIdx][0]) > 0) {
                                $bLoadAllPreviousEvents = $aModConfig['load_all_events_form'][$iFormIdx];
                            } elseif (isset($aModConfig['load_all_events'])) {
                                $bLoadAllPreviousEvents = $aModConfig['load_all_events'];
                            } 
                            if (strlen($aModConfig['save_status_form'][$iFormIdx]) > 0) {
                                $iSaveStatus = $aModConfig['save_status_form'][$iFormIdx];
                            } elseif (isset($aModConfig['save_status'])) { 
                                $iSaveStatus = $aModConfig['save_status'];
                            }
                            if (strlen($aModConfig['update_form'][$iFormIdx]) > 0) {
                                $bUpdateInstrument = $aModConfig['update_form'][$iFormIdx];
                            } elseif (isset($aModConfig['update'])) { 
                                $bUpdateInstrument = $aModConfig['update'];
                            }
                            break;
                        }
                    }
                } else {
                    $bLogic = false;
                    $aLoadStatus = $aModConfig['load_status'];
                    $iSaveStatus = $aModConfig['save_status'];
                }                

                // get status array for current record
                $grid_form_status_temp = Records::getFormStatus($project_id,array($record));
                $grid_form_status = $grid_form_status_temp[$record];

                // $bLoadData => true if status for current form is empty
                $bLoadData = false;
                if (isset($grid_form_status[$event_id][$instrument]) && strlen($grid_form_status[$event_id][$instrument][$repeat_instance]) == 0) {
                    $bLoadData = true;
                }

                // update instrument
                if (isset($grid_form_status[$event_id][$instrument]) && strlen($grid_form_status[$event_id][$instrument][$repeat_instance]) > 0 && $bUpdateInstrument) {
                    $bLoadData = true;
                }

                // load data only if status is empty
                if ($bLoadData) {
                    $iEventId = $event_id;
        
                    //  all fields to load
                    $aVisitFields = array();
                    // record_id
                    $aVisitFields[] = REDCap::getRecordIdField();
                    // date field
                    if ($Proj->longitudinal) {
                        $aVisitFields[] = $aModConfig['visit_date'];
                    }
                    // load all fields of current form
                    $aInstrumentFields = REDCap::getFieldNames($instrument);

                    foreach($aInstrumentFields as $sField) {
                        // skip some fields that should be empty 
                        if (in_array($sField,$aModConfig['excluded_fields'])) continue;
                        $aVisitFields[] = $sField;
                    }

                    // get event name by event_id
                    $sEventName = REDCap::getEventNames(true, true, $iEventId);

                    // array contains last filled in data
                    $aLastGood = array();
                    $aRepeatingInstancesData = array();
                    
                    // load all events for the current record
                    if ($Proj->longitudinal) {

                        $aDataDiag = json_decode(REDCap::getData($project_id, 'json', $record, $aVisitFields, null, null, false, false, false, false, false, false, false, false, false, array($aModConfig['visit_date'] => 'ASC')),true);

                        // return if record is empty
                        if (count($aDataDiag) == 0) {
                            return false;
                        }
                        // assign events to visit dates
                        $aEventNameDate = array();
                        foreach($aDataDiag as $aTmp) {
                            if (strlen($aTmp[$aModConfig['visit_date']]) > 0) {
                                $aEventNameDate[$aTmp['redcap_event_name']] = $aTmp[$aModConfig['visit_date']];
                            }
                        }
                        // create new array with visits and fill in empty visit_date (necessary for forms with repeating instances because visit_date will be empty for repeating instance)
                        $aDataDiagNew = array();
                        foreach($aDataDiag as $aTmp) {
                            // skip visits with empty date
                            if (strlen($aEventNameDate[$aTmp['redcap_event_name']]) == 0) continue;
                            if (strlen($aTmp[$aModConfig['visit_date']]) == 0) {
                                $aTmp[$aModConfig['visit_date']] = $aEventNameDate[$aTmp['redcap_event_name']];
                            }
                            $aDataDiagNew[$aEventNameDate[$aTmp['redcap_event_name']]][] = $aTmp;
                        }
                        unset($aDataDiag);
                        // sort by date (ascending)
                        ksort($aDataDiagNew, SORT_STRING);

                        // cut off future events 
                        $aPreviousEvents = array();
                        foreach($aDataDiagNew as $sVisitDate => $aVisit) {
                            foreach($aVisit as $aData) {
                                $aPreviousEvents[$sVisitDate][] = $aData;
                                // if there is a repeating instance with data, load the data from the current event
                                if (strlen($aData['redcap_repeat_instance']) > 0) {
                                    $sLastEvent = $aData['redcap_event_name'];
                                }
                            }
                            // break if current event is reached
                            if ($aData['redcap_event_name'] == $sEventName) {
                                $aCurrentInstrument = $aData;
                                break;
                            }                
                            $sLastEvent = $aData['redcap_event_name'];
                        }
                        unset($aDataDiagNew);
                        $aPreviousEvents2 = array();
                        // extract the last event
                        if (!$bLoadAllPreviousEvents) {
                            foreach($aPreviousEvents as $sVisitDate => $aVisit) {
                                foreach($aVisit as $aData) {
                                    if ($aData['redcap_event_name'] == $sLastEvent) {
                                        $aPreviousEvents2[$sVisitDate][] = $aData;
                                    }
                                }
                            }
                        } else {
                            $aPreviousEvents2 = $aPreviousEvents;
                        }
                        unset($aPreviousEvents);


                        foreach($aPreviousEvents2 as $sVisitDate => $aVisit) {
                            foreach($aVisit as $aData) {
                                $iEventId = REDCap::getEventIdFromUniqueEvent($aData['redcap_event_name']);

                                // break if current event is reached
                                if ($aData['redcap_event_name'] == $sEventName) {
                                    $aCurrentInstrument = $aData;
                                    break;
                                }                

                                $bValidData = false;
                                if ($bLogic) {
                                    if ($Proj->isRepeatingForm($iEventId, $instrument)) {
                                        $bValidData = REDCap::evaluateLogic($sLogic, $project_id, $record, $aData['redcap_event_name'], $aData['redcap_repeat_instance'], $instrument);
                                    } else {
                                        $bValidData = REDCap::evaluateLogic($sLogic, $project_id, $record, $aData['redcap_event_name']);
                                    }
                                } elseif (in_array($aData[$instrument.'_complete'],$aLoadStatus) && strlen($aData[$instrument.'_complete']) > 0) {
                                    $bValidData = true;
                                }
                                // load data if status of form matches the project setting
                                if ($bValidData) {
                                    $aLastGoodDate = DateTimeRC::datetimeConvert($aData[$aModConfig['visit_date']], 'ymd', substr($val_type, -3));
                                    if ($Proj->isRepeatingForm($iEventId, $instrument)) {
                                        $aLastGoodDate .= ' ('.$lang['data_entry_278'].$aData['redcap_repeat_instance'].')';
                                    }
                                    unset($aData[$aModConfig['visit_date']]);
                                    unset($aData['redcap_event_name']);
                                    $aLastGood = $aData;
                                }
                            }
                            // break outer loop if current event is reached
                            if ($aData['redcap_event_name'] == $sEventName) {
                                $aCurrentInstrument = $aData;
                                break;
                            }                
                        }
                        // special case: if repeating instances are active for current form and data exists for current form
                        // => load data of last instance of current form
                        if ($Proj->isRepeatingForm($iEventId, $instrument) && $aData['redcap_event_name'] == $sEventName) {
                          $aRepeatingInstancesData = $aVisit;
                        }
                      
                    } elseif ($Proj->isRepeatingForm($iEventId, $instrument)) {
                        $aRepeatingInstancesData = json_decode(REDCap::getData($project_id, 'json', $record, $aVisitFields),true);
                    }

                    if (count($aRepeatingInstancesData) > 0) {
                        $aVisit = array();
                        foreach($aRepeatingInstancesData as $aTmp) {
                            if ($aTmp['redcap_repeat_instance'] >= $repeat_instance || strlen($aTmp['redcap_repeat_instance']) == 0) {
                                continue;
                            }                
                            $aVisit[$aTmp['redcap_repeat_instance']] = $aTmp;
                        }
                        // sort by instance (ascending)
                        ksort($aVisit, SORT_NUMERIC);

                        $aVisit2 = array();
                        // extract the last visit
                        if (!$bLoadAllPreviousEvents) {
                            $aVisit2[] = array_pop($aVisit);
                        } else {
                            $aVisit2 = $aVisit;
                        }
                        unset($aVisit);

                        foreach($aVisit2 as $aData) {

                            $bValidData = false;
                            if ($bLogic) {
                                $bValidData = REDCap::evaluateLogic($sLogic, $project_id, $record, $aData['redcap_event_name'], $aData['redcap_repeat_instance'], $instrument);
                            } elseif (in_array($aData[$instrument.'_complete'],$aLoadStatus) && strlen($aData[$instrument.'_complete']) > 0) {
                                $bValidData = true;
                            }
                            
                            // load data if status of form matches the project setting
                            if ($bValidData) {
                                $aLastGoodDate = '';
                                if ($Proj->longitudinal) {
                                    $aLastGoodDate = DateTimeRC::datetimeConvert($aData[$aModConfig['visit_date']], 'ymd', substr($val_type, -3));
                                    unset($aData[$aModConfig['visit_date']]);
                                    unset($aData['redcap_event_name']);
                                }
                                $aLastGoodDate .= ' ('.$lang['data_entry_278'].$aData['redcap_repeat_instance'].')';
                                $aLastGood = $aData;
                            }
                        }
                    }

                    // save data if array is filled with old data
                    if (count($aLastGood) > 0) {

                        $aData2 = $aLastGood;
                        
                        // update instrument
                        if ($bUpdateInstrument && count($aCurrentInstrument) > 0) {
                            $aCurr = array();
                            foreach($aCurrentInstrument as $key => $val) {
                                if (isset($aLastGood[$key]) && strlen($aLastGood[$key]) > 0 && strlen($val) == 0) {
                                    $aCurr[$key] = $aLastGood[$key];
                                }
                            }
                            $aData2 = $aCurr;
                        }

                        $aData2[REDCap::getRecordIdField()] = $record;
                        if ($Proj->longitudinal) {
                            $aData2['redcap_event_name'] = $sEventName; 
                        }
                        // add instance field if form has repeating instances
                        if ($Proj->isRepeatingForm($iEventId, $instrument)) {
                            $aData2['redcap_repeat_instance'] = $repeat_instance;
                        }
                        // set status to project setting "save_status"
                        $aData2[$instrument.'_complete'] = $iSaveStatus;

                        $data = json_encode(array($aData2));
                        // save data
                        REDCap::saveData($project_id, 'json', $data, 'overwrite', 'YMD', 'flat', $group_id, true);

                        // show message that data was loaded from old visit
                        print ('<br /><div class="red">'.REDCap::escapeHtml($aModConfig['show_message']).'<br />'.$aLastGoodDate.'</div><br />');     
                        
                        return true;
                    }        
                }
            }
        }
    }
}
