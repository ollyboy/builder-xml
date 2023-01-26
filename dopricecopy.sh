#get the builder XML files, get runway plans, update price

# go to the main folder
cd /home/ec2-user/builder-xml

# log the start of a run
echo "---- Do price run" >> /tmp/dp-runcopy.log
date >> /tmp/dp-runcopy.log

# get the XML file, wake Perry up first
/usr/bin/php doxml.php console > dopricecopy.log 2>&1

#get the latest runway plans
/usr/bin/php runway_get_plan.php Hillwood-Prod >> dopricecopy.log 2>&1

#run the price compare, remove prod-post for dummy run
#/usr/bin/php builder.php HorizonDeerCreek-Prod prod-post >> doprice.log 2>&1
#/usr/bin/php builder.php HorizonDeerCreek-Demo David-SandBrock Coventry demo-post lot-update >> doprice.log 2>&1
/usr/bin/php builder.php Hillwood-Prod Highland-Harvest prod-post >> dopricecopy.log 2>&1
#/usr/bin/php builder.php HowardHughes-Prod prod-post >> doprice.log 2>&1
# example use - php builder.php <developer> <builder-1> builder-2> demo-post >> doprice.log 2>&1

# check for errors
cat dopricecopy.log | egrep 'ERROR|FATAL' >> /tmp/dp-runcopy.log
cat dopricecopy.log | egrep 'ERROR|FATAL' >  error.matchcopy.log

# send files to Gdrive
rclone copy . --include "*.match.*" runway-drop-files: >> dopricecopy.log 2>&1

# log ending times
date >> /tmp/dp-runcopy.log
echo "----- Do price end" >> /tmp/dp-runcopy.log
#
echo "----- Do price Warn" >> /tmp/dp-warncopy.log
date >> /tmp/dp-warncopy.log
cat dopricecopy.log | egrep 'WARN' >> /tmp/dp-warncopy.log

#end of sh
