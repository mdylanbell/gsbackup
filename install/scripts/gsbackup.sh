#!/bin/bash

##############################################################################
# gsbackup.sh - Backup script for (mt) Media Temple (gs) Grid-Service
# Version 1.0 - Written by Matthew Bell
#
# This script will back up files and/or databases, storing them in ~/backups.
# Input is thoroughly checked.  If specified files do not exist, or all
# required database information is not specified, the backup will exit with
# non-zero status. A backup log is kept of all actions after input
# validation.
#
# Once all input has been verified, a temporary environment is created in 
# /tmp, the databases are backed up (if specified), and then an archive is
# created.  The archive is a tar archive with bz2 compression, and is created
# in a way that extracts cleanly to a single directory which contains all of
# the backup files, database sql files, and the backup log.
#
# If S3 backup capabilities were installed and specified on the command line,
# the third party tool 's3cmd' is called to perform the upload to S3.
#
# All of the script options are outlined in the usage() function.
#
##############################################################################

# Set default S3 backup path here, at minimum must be /YOUR_BUCKET
S3_BACKUP_PATH=/YOUR_BUCKET/backups/websites

##############################################################################
# Don't modify anything past this line
##############################################################################

###########################
# Global variables
###########################

# Bash doesn't require these to be set, but these are the globals we use
VERBOSE=""
QUIET=""
S3_BACKUP=""
DB_BACKUP=""
ONE_DB_FILE=""
DB_DATABASES=""
DB_USER=""
DB_PASS=""
DIRECTORY=""
LABEL=""
NUM_BACKUPS=""

# These DO need initialized
NUM_DATABASES=0
DATE_STAMP="$(date +'%Y-%m-%d-%H%M%S')"

# Work around if HOME isn't set (called from php)
path=$HOME
if [ ! $path ]; then
    path=`pwd`
fi
declare -i SITE_ID=`echo $path | sed 's~.*/\([0-9]*\)/.*$~\1~'`

# This backup folder is created by the installer, don't modify unless
# you know what you're doing
BACKUP_DEST=/home/$SITE_ID/data/backups

main ()
{
    # Save command line for logging.
    local command_line="$0 $@"
    
    # The parse_options function sets up our global options and makes sure 
    # we have good data
    parse_options "$@"

    # Set up our temporary environment
    local temp_dir="/tmp/gsbackup.`date +%s%N`/$LABEL-backup-$DATE_STAMP"
    mkdir -p "$temp_dir"
    LOG="$temp_dir/gsbackup-$DATE_STAMP.log"

    # Echo the command to the log, but replace the password with some *s
    echo "Command: $command_line" | sed 's~ -p [^ ]*~ -p \*\*\*\*\*~' >> $LOG

    cd $temp_dir

    if [ $DB_BACKUP ]; then
        if [ ! $QUIET ]; then
            echo "Backing up database(s)..."
        fi

        # We know what their database host is
        local db_host=internal-db.s$SITE_ID.gridserver.com

        backup_database $db_host $DB_USER $DB_PASS "$DB_DATABASES"
    fi

    local tar_file="$BACKUP_DEST/$LABEL-$DATE_STAMP.tar.bz2"
    
    backup_files "$DIRECTORY" "$FILES" "$tar_file" "$(basename $temp_dir)"
    local result=$?

    if [[ $result != 0 ]]; then
        echo "Failed to create backup file.";
    else
    	if [ ! "$QUIET" ]; then
            echo "Successfully created backup file: $tar_file"
        fi

        if [ $S3_BACKUP ]; then
            if [ ! $QUIET ]; then
                echo "Uploading to s3."
            fi
            upload_to_s3 $tar_file $S3_BACKUP_PATH/$LABEL/
        fi

        if [ ! -z $NUM_BACKUPS ]; then
            prune_backups "$BACKUP_DEST" "$LABEL" "$NUM_BACKUPS"
        fi
    fi
    
    # Cleanup
    rm -rf `dirname $temp_dir`

    return $result
}

usage ()
{
    cat <<END
usage: gsbackup.sh -l label [optons]  files
options:
  # Database options:
    -d database             # Backup database, can be specified multiple times
    -D                      # If mutliple databases are specified, create
                            #    only 1 sql file instead of 1 per database
    -u database-user        # Specify database username
    -p database-password    # Speicfy database password

  # Output control:
    -v                      # Show all command output (can't be used with -q)
    -q                      # Don't show any output (can't be used with -v)

  # S3 commands:
    -S                      # Backup to Amazon S3
    -s S3_path              # S3 backup path including bucket, will be appended
                            #    with label specified as -l 
                            #    example: '-s /bucket/path' ends up as 
                            #    /bucket/path/label/backup.tar.bz2

  # Misc:
    -N number               # Number of backups with this label to keep.  If more
                            #    exist after creating backup, they will be deleted
    -C directory            # change working directory
                            #    (so you don't have to 'cd' first)
    -l label                # required, name of backup (gets appended with date)
    -h                      # Show this help

examples:
# this backs up media.domain.com and domain.com (and any other *domain.com
# directory) if you are in ~/domains
gsbackup.sh -l domain.com *domain.com

# this does same as above, from anywhere in path in your (gs)
gsbackup.sh -l domain.com-files -C ~/domains *domain.com

# Backup a database along with domain.com files
gsbackup.sh -l domain.com-with-db -C ~/domains -d dbxxxxx_mydb -u dbxxxxx -p dbpass *domain.com

# Backup all databases associated with your (gs)
gsbackup.sh -l all_databases -a -u dbxxxxx -p dbpass *domain.com

# Backup 3 databases, but save them all to only 1 sql file
gsbackup.sh -l my_db_backup -d db1 -d db2 -d db3 -D -u dbxxxxx -p dbpass

# Backup domain.com files and databases to S3 - there will be 2 sql files.
# On S3, the file will be: MY_BUCKET/backups/websites/domain.com/<backup>.tar.bz2
gsbackup.sh -l domain.com -d mydb1 -d mydb2 -u dbxxxxx -p dbpass -C ~/domains -S -s /MY_BUCKET/backups/websites *domain.com
END
}

##############################################################################
# parse_options function
#
# Parses command line input, verifies conditions and that inputs are sane
##############################################################################

parse_options ()
{
    while getopts "avqhSDd:u:p:C:N:s:l:" options; do
        case $options in
            v) VERBOSE=1;;
            q) QUIET=1;;
            S) S3_BACKUP=1;;
            D) ONE_DB_FILE=1;;
            d) DB_DATABASES="$OPTARG $DB_DATABASES"; 
               NUM_DATABASES=$((NUM_DATABASES+1));;
            u) DB_USER=$OPTARG;;
            p) DB_PASS=$OPTARG;;
            C) DIRECTORY=$OPTARG;;
            N) NUM_BACKUPS=$OPTARG;;
            s) S3_BACKUP_PATH=$OPTARG;;
            l) LABEL=$OPTARG;;
            h) usage; exit 1;;
            \?) usage; exit 1;;
            *) usage; exit 1;;
        esac
    done
    shift $((OPTIND-1))

    # Make sure we're on GS
    if [ $SITE_ID -eq 0 ]; then
        echo "Unable to determine site ID.  Are you on a (gs)?"
        exit 1
    fi

    # Make sure we have required label
    if [ ! "$LABEL" ]; then
        echo "Error: Label is required.  Use '-l' to specify a label."
        exit 1
    fi

    # Make sure there is no whitespace in the label
    if `echo $LABEL | egrep ' \n\r\t' >> /dev/null 2>&1`; then
        echo "Label may not contain whitespace. Exiting."
        exit 1
    fi

    # If any database information is supplied, make sure we have everything 
    # we need for a database backup
    local dbinfo=0
    if [ $NUM_DATABASES -ne 0 ]; then dbinfo=$((dbinfo+1)); fi
    if [ $DB_USER ]; then dbinfo=$((dbinfo+1)); fi
    if [ $DB_PASS ]; then dbinfo=$((dbinfo+1)); fi
    case $dbinfo in
       1|2)
          echo "Error: You must specify all of -d, -u, -p for database backups." 
          exit 1;;
       3) DB_BACKUP=1;;
    esac

    # Use the specified directory, or capture the current one
    if [ $DIRECTORY ]; then
        if [ ! -d "$DIRECTORY" ]; then
            echo "Error: '$DIRECTORY': directory does not exist."
            exit 1
        fi
    else
        DIRECTORY=`pwd`
    fi

    # Make sure they're not being tricksy with output level
    if [ "$QUIET" -a "$VERBOSE" ]; then
        echo "Error: You can't specify -q and -v at the same time."
        exit 1
    fi

    # Well, this is awkward.  This shouldn't happen unless user fudged something
    if [ ! -d "$BACKUP_DEST" ]; then
        echo "Error: '$BACKUP_DEST': backup destination does not exist. Please create it."
        exit 1
    fi

    # Make sure the files they specified are there
    cd $DIRECTORY
    FILES=$@
    for file in $FILES; do
        if [ ! -e $file ]; then
            echo "Error: '$file': file does not exist."
            exit 1
        fi
    done

    if [ ! "$FILES" -a $NUM_DATABASES -eq 0 ]; then
        echo "Error: No databases or files specified.  Nothing to do."
        exit 1
    fi
    
    # Test that NUM_BACKUPS is a number > 0 (if set)
    if [ ! -z $NUM_BACKUPS ]; then
        if [[ $NUM_BACKUPS != ${NUM_BACKUPS//[^0-9]/} ]]; then
            echo "Error: Value entered for number of backups is not a number!"
            exit 1
        fi
    fi
}



##############################################################################
# backup_database function
#
# Backs up mysql database(s) to our temp directory.  Prints any errors, as
# well as logging them to the backup log.
#
# Parameters: host, user, password, databases
##############################################################################

backup_database ()
{
    local db_host=${1/ //}
    local db_user=${2/ //}
    local db_pass=${3/ //}
    local databases=$4

    local output_file=""

    local dump_string="--add-drop-table -h $db_host -u $db_user -p$db_pass"
    local error_log="database_errors.log"

    if [ "$ONE_DB_FILE" ]; then
        output_file=$LABEL-$DATE_STAMP.sql
        dump_string="$dump_string --databases $databases"
        echo "mysqldump command = mysqldump $dump_string" | \
            sed 's~ -p[^ ]*~ -p\*\*\*\*\*~' >> $LOG
        mysqldump $dump_string >$output_file 2>>$error_log

    else
        for db in $databases; do
            output_file=$db-$DATE_STAMP.sql
            local my_dump_string="$dump_string $db"
            echo "mysqldump command = mysqldump $my_dump_string > $output_file" | \
                sed 's~ -p[^ ]*~ -p\*\*\*\*\*~' >> $LOG
            mysqldump $my_dump_string >$output_file 2>>$error_log
        done
    fi

    # If we have a non-zero size error_log, Notify about errors and log them
    if [ -s $error_log ]; then
        echo "**** There were errors with mysql backup:"
        cat $error_log
        echo "**** Continuing ****"

        echo "*** ERRORS backing up mysql ***" >> $LOG
        cat $error_log >> $LOG
        echo "*** End of mysql errors. Performing the rest of the backup ***" \
            >> $LOG
        LABEL="DB_ERRORS-$LABEL"

        # Remove any empty output files - we don't want user to assume data due 
        # to existance of a file
        output_file="*.sql"
        if [ -e $output_file ]; then
            for file in `ls $output_file`; do
                if [ ! -s "$file" ]; then
                    rm $file
                fi
            done
        fi
   fi

    rm $error_log
}


##############################################################################
# backup_files function
#
# Creates the archive in tar format with bz2 compression. Takes the backup
# (point of reference), and a list of files to create an archive which
# extracts into a single directory.  It does this by creating symlinks in our
# temporary directory to the specified files.
#
# Parameters: directory, files, full path to output tar file, and the
#    "temporary" directory we're tarring up.
##############################################################################

backup_files ()
{
    local directory=$1
    local files=$2
    local archive_file=$3
    local tar_input=$4

    # Make symlinks to specified files, so the tarball extracts into only 1 dir
    for file in $files; do
        ln -s $directory/$file $file
    done

    local tar_file=`echo $archive_file | sed "s/\(.*\)\.bz2$/\1/"`

    # Specify -h to have tar treat symlinks like regular files
    # Also, exclude the log, because we want to capture the tar output to the log,
    #   and we can't tar up if we're still writing the log.  Don't worry, we'll
    #   add it later!  Unfortunately this means a separate bzip2 operation.
    local tar_string="-chv --exclude=$(basename $LOG) -f $tar_file $tar_input"

    echo "tar output follows" >> $LOG
    echo "------------------------------------------------------------------" \
           >> $LOG

    # Finally, create the backup
    if [ ! "$QUIET" ]; then
        echo "Creating file backup archive."
    fi

    cd ..
    
    if [ "$VERBOSE" ]; then
        tar $tar_string | tee $LOG
    else
        tar $tar_string >> $LOG 2>&1
    fi

    if [[ $? != 0 ]]; then
        # tar failed
        rm $tar_file
        return 1
    fi

    echo "------------------------------------------------------------------" \
           >> $LOG

    # Append the log, do the bzip2, and delete the raw .tar file (leaving behind the tar.bz2)
    tar -rf $tar_file $tar_input/$(basename $LOG)
    bzip2 $tar_file
    rm -f $tar_file

    return 0
}


##############################################################################
# upload_to_s3 function
#
# Uploads the tar file to Amazon s3.
#
# Parameters: source tar file, remote path including bucket
#
# path should be formatted like "/BUCKET/path/to/destination"
# The resulting file is stored to 
#   /BUCKET/path/to/destination/tar_file.tar.bz2
##############################################################################

upload_to_s3 ()
{
    local tar_file=$1
    local s3_remote_path=$2
   
    s3_remote_path=$(echo $s3_remote_path | sed 's~//~/~g' | sed 's~^/~~g')

    local s3_string="-c /home/$SITE_ID/users/.home/.s3cfg -H put $tar_file s3://$s3_remote_path" 

    # We have to set the python path if getting called from web
    SAVED_PYTHONPATH=$PYTHONPATH
    export PYTHONPATH=/home/$SITE_ID/data/python/lib:/home/$SITE_ID/data/python/bin

    # Run s3cmd
    /home/$SITE_ID/data/python/bin/s3cmd $s3_string

    # Be nice and put the PYTHONPATH back
    export PYTHONPATH=$SAVED_PYTHONPATH
}


prune_backups ()
{
    local directory=$1
    local label=$2
    local keep_num_backups=$3

    cd $directory

#    local backups=`ls -rc $label-*` 
    local backups=`find $directory -maxdepth 1 -type f -regex ".*/$label-[0-9][0-9][0-9][0-9]-[0-9][0-9].*" -printf '%C@ %h/%f\n' | sort -n | cut -d ' ' -f 2`
    local num_backups=0
    for i in $backups; do num_backups=$((num_backups+1)); done
    
    local backups_to_delete=$((num_backups - keep_num_backups))

#echo "Pruning backups"
#echo "directory = $directory"
#echo "label = $label"
#echo "keep_num_backups = $keep_num_backups"
#echo "num_backups = $num_backups"
#echo "backups = $backups"
#echo "backups_to_delete = $backups_to_delete"

    if [ $backups_to_delete -gt 0 ]; then
        # There are backups to delete
        for i in $backups; do
            if [ $backups_to_delete -gt 0 ]; then
                rm $i;
            else
                break
            fi
            backups_to_delete=$((backups_to_delete-1))
        done
    fi
}

main "${@}"
exit $?
