# REDCap module to pre-populate an instrument with data from a previous event / instance
When an empty instrument is displayed the module loads all previous event / instance data of this instrument. It shows a message above the instrument that the user should review the data and change it when necessary.   

There are two different modes:
In a longitudinal project instruments will be pre-populated with data from the most current event data.
In a classic project instruments will be pre-populated with data from the last instance.

Caution: Previous data is copied to the current event before you save the instrument. So, if you cancel the data entry of the instrument, the previous data is still there!
 
## Prerequisites
- REDCap with external modules framework (>= v.8.0.0)
- User must have edit permission for instrument to be pre-populated
- Longitudinal project:
  - A field containing the visit date (or datetime)
  - Instruments to be pre-populated and the instrument containing the visit date must be assigned to the same event  
  - The visit date for the current event must already be entered before you can pre-populate an empty instrument
- Classic project:
  - Instruments to be pre-populated must be configured as repeatable instruments  

## Installation
- Go to **Control Center > External Modules** and download the module from the repository
- Enable the module
- For each project you want to use this module, go to the project home page, click on **External Modules** link, and then enable the module for that project.

## Configuration
- Select field that contains visit date (with date or datetime validation type) (only necessary for longitudinal projects)
- Select instruments to be pre-populated (required)
- Select one ore more states that previous data must match (e.g. if you want to load previous data only if the instrument is "complete", then choose "complete") (required)
- Select state for pre-populated instrument (required)
- Type message that appears above prefilled instrument (e.g. a warning that the data was copied) (required)

## Example (longitudinal project)

| Event | Visit Date | State |
| --- | --- | --- |
| 01 | 2015-01-01 | Completed |
| 02 | 2017-01-01 | Completed |
| 03 | 2016-01-01 | Completed |
| 04 | 2018-01-01 |  |

- Configuration: 
  - Instrument must be "completed" => instrument of event "04" will be pre-populated with data from event "02", because this is the most current event data with "completed" state. 