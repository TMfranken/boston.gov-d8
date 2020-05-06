#!/bin/bash

###############################################################
#  These commands need to be run as normal user from lando.yml.
#
#  NOTE: THIS SCRIPT SHOULD BE RUN INSIDE THE CONTAINER.
#
#  These commands install Start the npm processes: gulp and stylus.
#
#  PRE-REQUISITES:
#     - node:10 docker container for patterns is created and started, and
#     - main boston.gov repo is already cloned onto the host machine, and
#     - .lando.yml and .config.yml files are correctly configured.
#
#  Basic workflow:
#     1. Start the fractal service.
###############################################################

    # Include the utilities file/libraries.
    # This causes the .lando.yml and .config.yml files to be read in and stored as variables.
    REPO_ROOT="${LANDO_MOUNT}"
    . "${LANDO_MOUNT}/scripts/cob_build_utilities.sh"
    . "${LANDO_MOUNT}/hooks/common/cob_utilities.sh"

    printf "\n"
    printf "ref: $(basename "$0")\n"
    if [[ "${patterns_local_build}" != "true" ]] && [[ "${patterns_local_build}" != "True" ]] && [[ "${patterns_local_build}" != "TRUE" ]]; then
        printout "INFO" "Patterns library will not be deployed.."
        exit 0
    fi
    printf "\n${LightPurple}       ================================================================================${NC}\n"
    printout "LANDO" "Project Event - patterns post-start\n"
    printf "${LightPurple}       ================================================================================${NC}\n"

    # Install the patterns app.
    printout "INFO" "Starting Patterns library - will build stylus(css) and minify js files."
    printout "INFO" "Wait for files."
    # Becuse the node container builds after the database and appserver containers, we have to
    # wait for those processes to complete first.  TODO: Make appserver and database dependent on the node server.
    x=0
    while [[ ! -e  ${patterns_local_repo_local_dir}/package.json ]]; do
        x=$((x+10))
        printf "."
        if [[ $x -gt 1800 ]];then printf "\nERR - timout in $(basename "$0")\n"; exit 1; fi
        sleep 10
    done

    # Run an initial build to be sure everything is there.
    printout "INFO" "Build Stuff."
    cd ${patterns_local_repo_local_dir} && npm run preinstall && npm run fractal-build && npm run gulp-build

    printout "INFO" "Create the fractal server and start watch file system for updates."
    # Make a link to the patterns public folder in the main website (served from appserver)
    # So that fleet is available from the webserver in the appserver container. e.g. https://boston.lndo.site/patterns.
    if [[ ! -L ${project_docroot}/patterns ]]; then
        if [[ -e ${project_docroot}/patterns ]]; then rm -rf ${project_docroot}/patterns; fi
        ln -s ${patterns_local_repo_local_dir}/public ${project_docroot}/patterns
    fi
    # Fire up the watchers: Note this process will remain running until the container is stopped.
    # (and the container will stop if this process terminates for any reason)
    cd ${patterns_local_repo_local_dir} && npm run dev
