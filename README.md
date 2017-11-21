# REDCap module to pre-populate an instrument with data from a previous event
This module pre-populates one or more instruments in a longitudinal project with data from a previous event.
When an instrument with empty status (i.e. no data) is called, the module loads all previous event data of this instrument.
It will pre-populate the instrument with the most current event data and shows a message above the instrument that the user should review the old data and change it when necessary.   

Caution: Previous data is copied to the current event before you save the instrument. So, if you cancel the data entry of the instrument, the previous data is still there!
 
## Prerequisites
- REDCap with external modules framework (>= v.8.0.0)
- Longitudinal project with a field containing the visit date (or datetime)
- Instruments to be pre-populated and the instrument containing the visit date must be assigned to the same event  
- The visit date for the current event must already be entered before you can pre-populate an empty instrument 
- User must have edit permission for instrument to be pre-populated

## Installation
- Go to **Control Center > External Modules** and download the module from the repository
- Enable the module
- For each project you want to use this module, go to the project home page, click on **External Modules** link, and then enable the module for that project.

## Configuration
- Select field that contains visit date (with date or datetime validation type) (required)
- Select instruments to be pre-populated (required)
- Select one ore more states that previous data must match (e.g. if you want to load previous data only if the instrument is "complete", then choose "complete") (required)
- Select state for pre-populated instrument (required)
- Type message that appears above prefilled instrument (e.g. a warning that the data was copied) (required)