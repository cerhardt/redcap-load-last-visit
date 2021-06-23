# REDCap module to pre-populate an instrument with data from a previous event / instance
When an empty instrument is displayed the module loads all previous event / instance data of this instrument. It shows a message above the instrument that the user should review the data and change it when necessary.   

There are two different modes:
In a longitudinal project instruments will be pre-populated with data from the most current event data. If the instrument is repeating in the current event, a new instance of this instrument will be pre-populated with data from the last instance.
In a classic project instruments will be pre-populated with data from the last instance.

New features in v2.0:
- override global settings per instrument
- define custom filters for events/instances to be loaded. 
- limit the scope of previous events/instances to all events/instances or just the last one.

Caution: Previous data is copied to the current event before you save the instrument. If you cancel the data entry of the instrument, the previous data is still there!
 
## Prerequisites
- REDCap with external modules framework (>= v.8.0.0)
- User must have edit permission for instrument to be pre-populated
- Longitudinal project:
  - A field containing the visit date (or datetime)
  - Instruments to be pre-populated and the instrument containing the visit date must be assigned to the same event  
  - The visit date for the current event must already be entered before you can pre-populate an empty instrument
- Classic project:
  - Instruments to be pre-populated must be configured as repeatable instruments  
- Caution: This module does not work with repeating events  

## Installation
- Go to **Control Center > External Modules** and download the module from the repository
- Enable the module
- For each project you want to use this module, go to the project home page, click on **External Modules** link, and then enable the module for that project.

## Configuration (since v2.0)
- Select field that contains visit date (with date or datetime validation type) (only necessary for longitudinal projects)
- Global settings: these settings are global defaults and can be overwritten by an instrument setting
  -  Fields to be excluded (only global)
  -  Select one or more states for earlier data (e.g. if you want to load previous data only if the instrument is "complete", then choose "complete") 
  -  Select the scope of previous events/instances (all / just the last one) (required)
  -  Select state for pre-populated instruments 
  -  Type message that appears above prefilled instrument (e.g. a warning that the data was copied) (required)
- Add instruments and instrument settings:
  -  Select instrument to be pre-populated (required)
  -  Option 1: Select one or more states for earlier data if you want to override the global setting 
  -  Option 2: Type a custom filter (same syntax like branching logic)  
  -  Select the scope of previous events/instances if you want to override the global setting
  -  Select state for pre-populated instruments if you want to override the global setting


## Example 1 (longitudinal project)

| Event | Visit Date | State |
| --- | --- | --- |
| 01 | 2015-01-01 | Completed |
| 02 | 2017-01-01 | Completed |
| 03 | 2016-01-01 | Completed |
| 04 | 2018-01-01 |  |

- Configuration: 
  - Instrument must be "completed" => instrument of event "04" will be pre-populated with data from event "02", because this is the most current event data with "completed" state.
  
## Example 2 (classic project)

| Instance | User | Prepopulate |
| --- | --- | --- |
| 01 | joe | 1 |
| 02 | paul | 0 |
| 03 |  |  |

- Configuration: 
   - custom filter [user] = [user-name] and scope is set to "all": instance "03" will be pre-populated with data from instance "01" if the logged in user is "joe"
   - custom filter [prepopulate] = "1" and scope is set to "all": instance "03" will be pre-populated with data from instance "01"
   - custom filter [prepopulate] = "1" and scope is set to "just the last one": instance "03" will not be pre-populated because instance "02" doesn't match the custom filter