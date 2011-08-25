#!/bin/bash

##############################################################################
# install.sh - installer for gsbackup.sh
#
# Version 1.1 : 10/11/2010
# Written by Matthew Bell
#
# Installs gsbackup utility.  Creates ~/bin and copies the backup scripts,
# modifies ~/.bash_profile to include ~/bin on your PATH, and creates backup
# directory to hold your backups.
#
# Amazon s3 support is optional (install by running 'install.sh --with-s3').
# If specified, this creates ~/data/python with bin and lib directories, 
# updates .bash_profile, and installs some python libs as well as third-party 
# s3cmd tool.
#
# s3cmd is free software distributed under GPLv2, and is available from
# http://s3tools.org/
#
# I don't suggest modifying anything in this script.
#
##############################################################################

# Check path to make sure we're on a (gs)

path=$HOME
if [ ! $path ]; then
    path=`pwd`
fi
declare -i SITE_ID=`echo $path | sed 's~.*/\([0-9]*\)/.*$~\1~'`

# Todo: make this an input variable...
SITE_ROOT=/home/$SITE_ID
HOME_PATH=$SITE_ROOT/users/.home
BACKUP_PATH=$SITE_ROOT/data/backups

# if ! echo $path | egrep '/home/[0-9]+/' >> /dev/null 2>&1; then
#    echo "You don't appear to be on a (gs) system."
#    exit 1
# fi

PYTHONDIR=$SITE_ROOT/data/python
UNIQ_STRING=`date +%s%N`
READLINK=`readlink -f $0`;
INSTALLDIR=`dirname $READLINK`
INSTALL_S3=0

# Check command line arguments
if [ "$1" == "--with-s3" ]; then
    INSTALL_S3=1
elif [ "$1" == "--help" -o "$1" == "-h" ]; then
    cat <<END
Installs gsbackup utility.  Creates ~/bin and copies the backup scripts, 
modifies ~/.bash_profile to include ~/bin on your PATH, and creates ~/backup
directory to hold your backups.

Amazon s3 support is optional (install by running 'install.sh --with-s3').
If specified, this creates ~/data/python with bin and lib directories, updates
.bash_profile, and installs some python libs as well as third-party s3cmd tool.

Options:
    --with-s3           # Installs Amazon s3 upload support via third-party
                        # s3cmd, and required python modules.

    --help, -h          # This help message
END
fi

# Install S3 support if specified
if [ $INSTALL_S3 -eq 1 ]; then
    echo "Installing s3 backup functionality..."
    
    TEMPDIR=/tmp/gsbackup.$UNIQ_STRING
    S3CMD=s3cmd-0.9.9.91
    SETUPTOOLS=setuptools-0.6c11

    # Extract our packages into temp
    mkdir $TEMPDIR
    cd $TEMPDIR
    for package in $INSTALLDIR/packages/*; do
        tar xzf $package
    done

    # Create python dirs
    if [ ! -d $PYTHONDIR/lib -o ! -d $PYTHONDIR/bin ]; then
        echo "Creating python environment in ~/data/python"
        if [ ! -d $PYTHONDIR/lib ]; then
            mkdir -p $PYTHONDIR/lib
        fi
        if [ ! -d $PYTHONDIR/bin ]; then
            mkdir -p $PYTHONDIR/bin
        fi
    fi

    # Modify .bash_profile
    if ! grep 'gsbackup-with_s3' $HOME_PATH/.bash_profile >> /dev/null 2>&1; then 
        echo "Modifying your ~/.bash_profile"
        cat >> $HOME_PATH/.bash_profile <<END

### gsbackup-with_s3 BEGIN ###
export PYTHONPATH=$PYTHONDIR/lib:$PYTHONDIR/bin:\$PYTHONPATH
export PATH=\$PATH:$PYTHONDIR/bin:\$HOME/bin
### gsbackup-with_s3 END ###

END
    fi

    export PYTHONPATH=$PYTHONDIR/lib:$PYTHONDIR/bin:$PYTHONPATH
    export PATH=$PATH:$PYTHONDIR/bin:$HOME_PATH/bin

    # Create .pydistutils.cfg if it doesn't exist
    if [ ! -e $HOME_PATH/.pydistutils.cfg ]; then
        echo "Creating ~/.pydistutils.cfg"
        cat >> $HOME_PATH/.pydistutils.cfg <<END
### gsbackup BEGIN ###
[install]
install_lib = $PYTHONDIR/lib
install_scripts = $PYTHONDIR/bin
### gsbackup END ###
END
    fi

    # Install setuptools for easy_install, elementtree from easy_install, and s3cmd
    cd $TEMPDIR/$SETUPTOOLS
    echo "Installing python setuptools, required elementtree python package, and s3cmd"
    /usr/bin/env python setup.py -q install >> /dev/null 2>&1
    easy_install -q elementtree >> /dev/null 2>&1
    cd $TEMPDIR/$S3CMD
    S3CMD_PACKAGING=1 /usr/bin/env python setup.py -q install >> /dev/null 2>&1

    if s3cmd --version >> /dev/null 2>&1; then
        echo "Installed s3cmd successfully."
    else
        echo "There was a problem installing s3cmd :("
        exit 1
    fi
else
    # They didn't install S3 but we still need to add ~/bin to PATH
    if ! grep 'gsbackup-no_s3' $HOME_PATH/.bash_profile >> /dev/null 2>&1; then
        echo "Modifying your ~/.bash_profile"
        cat >> $HOME_PATH/.bash_profile <<END
    
### gsbackup-no_s3 BEGIN ###
export PATH=\$PATH:\$HOME/bin
### gsbackup-no_s3 END ###

END
    fi
fi

# Create bin directory if it doesn't exist
if [ ! -d $HOME_PATH/bin ]; then
    echo "Creating ~/bin directory"
    mkdir $HOME_PATH/bin
fi

# Copy scripts
echo "Installing 'gsbackup.sh' into ~/bin"
cp $INSTALLDIR/scripts/gsbackup.sh $HOME_PATH/bin
chmod 755 $HOME_PATH/bin/gsbackup.sh

echo "Installing 'gsbackup-auto.sh' into ~/bin"
cp $INSTALLDIR/scripts/gsbackup-auto.sh.example $HOME_PATH/bin

# Create backups directory if it doesn't exist
if [ ! -d $BACKUP_PATH ]; then
    echo "Creating directory where backups will live: $BACKUP_PATH"
    mkdir $BACKUP_PATH
fi

# Clean up temp
if [ $INSTALL_S3 -eq 1 ]; then
    rm -rf $TEMPDIR
fi

echo "All done!"
