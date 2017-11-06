# REDCap module to prefill an empty instrument
This module prefills an empty instrument with data of the last visit. 
If repeating instances are enabled for the intrument, it prefills the instrument with data from the last instance of the current event.

## Prerequisites
- REDCap with external modules framework (>= v.8.0.0)
- Longitudinal project with a field containing the visit date
- Instruments to be prefilled (and the visit date) must be assigned to longitudinal visits
- The visit date for the current visit must already be entered before you can edit the instrument to be prefilled 

## Installation
- Clone this repo into to `<redcap-web-root>/modules/load_visit_data_v1.0`.
- Go to **Control Center > External Modules** and enable module.
- For each project you want to use this module, go to the project home page, click on **External Modules** link, and then enable "Prefill instruments with data of last visit" for that project.

## Configuration
- select field that contains visit date (with date validation type) (required)
- select instruments to be prefilled (required)
- select one ore more states that instruments must match (i.e. if you want to load old visit data only if the instrument is "complete", then choose "complete") (required)
- select state for prefilled instrument (required)
- type message that appears above prefilled instrument (e.g. a warning that the data was copied) (required)