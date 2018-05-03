<?php
namespace HIH\LoadLastVisitData;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

use \REDCap as REDCap;
use \Records as Records;
use \DateTimeRC as DateTimeRC;

class LoadLastVisitData extends AbstractExternalModule {

    function hook_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {

        if (isset($project_id)) {
            
            global $Proj, $lang, $user_rights;

            if (empty($record)) {
                $record = $_GET['id'];
            }
            if (empty($record)) {
                return;
            }               
            // load data only if user has edit permission for instrument / survey
            if ($user_rights['forms'][$instrument] != '1' && $user_rights['forms'][$instrument] != '3') {
                return;
            }
            
            // load module configuration
            $aModConfigRaw = $this->getProjectSettings($project_id);
            $aModConfig = array();
            foreach($aModConfigRaw as $sKey => $aTmp) {
                $aModConfig[$sKey] = $aTmp['value'];
            }

            if (in_array($instrument,$aModConfig['forms'])) {
    		
                // get status array for current record
                $grid_form_status_temp = Records::getFormStatus($project_id,array($record));
                $grid_form_status = $grid_form_status_temp[$record];

                // $bLoadData => true if status for current form is empty
                $bLoadData = false;
                if (isset($grid_form_status[$event_id][$instrument]) && strlen($grid_form_status[$event_id][$instrument][$repeat_instance]) == 0) {
                    $bLoadData = true;
                }

                // load data only if status is empty
                if ($bLoadData) {
                    if ($Proj->longitudinal) {
                        $val_type = $Proj->metadata[$aModConfig['visit_date']]['element_validation_type'];
                        if (substr($val_type, 0, 4) !== 'date') return;
                    }
                                        
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
                    $sEventName = REDCap::getEventNames(true, true, $event_id);

                    // array contains last filled in data
                    $aLastGood = array();

                    // load all events for the current record
                    if ($Proj->longitudinal) {

                        $aDataDiag = json_decode(REDCap::getData($project_id, 'json', $record, $aVisitFields, null, null, false, false, false, false, false, false, false, false, false, array($aModConfig['visit_date'] => 'ASC')),true);

                        // return if record is empty
                        if (count($aDataDiag) == 0) {
                            return;
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
                        // sort by date (ascending)
                        ksort($aDataDiagNew, SORT_STRING);

                        foreach($aDataDiagNew as $aVisit) {
                            foreach($aVisit as $aData) {
                        
                                // break if current event is reached
                                if ($aData['redcap_event_name'] == $sEventName) {
                                    break;
                                }                
                                
                                // load data if status of form matches the project setting
                                if (in_array($aData[$instrument.'_complete'],$aModConfig['load_status']) && strlen($aData[$instrument.'_complete']) > 0) {
                                    $aLastGoodDate = DateTimeRC::datetimeConvert($aData[$aModConfig['visit_date']], 'ymd', substr($val_type, -3));
                                    unset($aData[$aModConfig['visit_date']]);
                                    unset($aData['redcap_event_name']);
                                    $aLastGood = $aData;
                                }
                            }
                            // break outer loop if current event is reached
                            if ($aData['redcap_event_name'] == $sEventName) {
                                break;
                            }                
                        }

                        // special case: if repeating instances are active for current form and data exists for current form
                        // => load data of last instance of current form
                        if (isset($aData['redcap_repeat_instance']) && $aData['redcap_event_name'] == $sEventName) {
                          foreach($aVisit as $aData) {
                              // break if current instance is reached
                              if ($aData['redcap_repeat_instance'] >= $repeat_instance || strlen($aData['redcap_repeat_instance']) == 0) {
                                  break;
                              }                
                              
                              // load data if status of form matches the project setting
                              if (in_array($aData[$instrument.'_complete'],$aModConfig['load_status']) && strlen($aData[$instrument.'_complete']) > 0) {
                                  $aLastGoodDate = DateTimeRC::datetimeConvert($aData[$aModConfig['visit_date']], 'ymd', substr($val_type, -3));
                                  $aLastGoodDate .= ' ('.$lang['data_entry_278'].$aData['redcap_repeat_instance'].')';
                                  unset($aData[$aModConfig['visit_date']]);
                                  unset($aData['redcap_event_name']);
                                  $aLastGood = $aData;
                              }
                          }
                        }

                    } else {
                        $aDataDiag = json_decode(REDCap::getData($project_id, 'json', $record, $aVisitFields),true);
                        $aVisit = array();
                        foreach($aDataDiag as $aTmp) {
                            if (strlen($aTmp['redcap_repeat_instance']) > 0) {
                                $aVisit[$aTmp['redcap_repeat_instance']] = $aTmp;
                            }
                        }
                        // sort by date (ascending)
                        ksort($aVisit, SORT_NUMERIC);

                        foreach($aVisit as $aData) {
                            // break if current instance is reached
                            if ($aData['redcap_repeat_instance'] >= $repeat_instance || strlen($aData['redcap_repeat_instance']) == 0) {
                                break;
                            }                
                            
                            // load data if status of form matches the project setting
                            if (in_array($aData[$instrument.'_complete'],$aModConfig['load_status']) && strlen($aData[$instrument.'_complete']) > 0) {
                                $aLastGoodDate = ' ('.$lang['data_entry_278'].$aData['redcap_repeat_instance'].')';
                                $aLastGood = $aData;
                            }
                        }
                    }
                    
                    // save data if array is filled with old data
                    if (count($aLastGood) > 0) {
                    
                        $aData2 = $aLastGood;
                        $aData2[REDCap::getRecordIdField()] = $record;
                        if ($Proj->longitudinal) {
                            $aData2['redcap_event_name'] = $sEventName; 
                        }
                        // add instance field if form has repeating instances
                        if (isset($aData2['redcap_repeat_instance'])) {
                            $aData2['redcap_repeat_instance'] = $repeat_instance;
                        }
                        // set status to project setting "save_status"
                        $aData2[$instrument.'_complete'] = $aModConfig['save_status'];
                        $data = json_encode(array($aData2));
                        // save data
                        REDCap::saveData($project_id, 'json', $data, 'overwrite', 'YMD', 'flat', $group_id, true);
                    
                        // show message that data was loaded from old visit
                        print ('<br /><div class="red">'.REDCap::escapeHtml($aModConfig['show_message']).'<br />'.$aLastGoodDate.'</div><br />');     
                    }        
                }
            }
        }
    }
}
