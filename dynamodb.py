#!/usr/bin/env python
import os
import sys
import optparse
import boto3
import uuid
import json
import collections
import signal
import time
import botocore
from pprint import pprint
from datetime import datetime
from datetime import timedelta
from os.path import expanduser

__VERSION__ = '1.0.0'

aws_response = {}

def parse_options():

    global options

    version = 'check_dynamodb, Version %s' %__VERSION__

    parser = optparse.OptionParser()
    parser.add_option("-w", "--warning", default='', help="Warning threshold value to be passed for the check. For multiple warning thresholds, enter a comma separated list.")
    parser.add_option("-c", "--critical", default='', help="Critical threshold value to be passed for the check. For multiple critical thresholds, enter a comma separated list.")
    parser.add_option("-V", "--version", action='store_true', help="Display the current version of check_dynamodb")
    parser.add_option("-v", "--verbose", action='store_true', help="Display more information for troubleshooting.")
    parser.add_option("-S", "--statistics", help="The metric statistics. For multiple statistics, Enter a comma delimited list. (Average | Sum | Minimum | Maximum)")
    parser.add_option("-P", "--period", help="The period of time you would like to check against in minutes.")
    parser.add_option("-t", "--timeout", default="10", help="Set the timeout duration in seconds. Defaults to never timing out.")
    parser.add_option("-n", "--metricname", help="The name of the metric you want to check")
    parser.add_option("-I", "--instanceid", help="The unique ID of the instance you want to monitor")
    parser.add_option("-m", "--minimum", default='', help="The minimum value used for performance data graphing.")
    parser.add_option("-M", "--maximum", default='', help="The maximum value used for performance data graphing.")
    parser.add_option("-k", "--accesskeyid", help="Your Amazon Access Key ID.")
    parser.add_option("-K", "--secretaccesskey", help="Your Amazon Secret Access Key.")
    parser.add_option("-r", "--region", help="Your Amazon Region Name.")
    parser.add_option("-F", "--configfile", help="The file path of your AWS configuration file.")
    parser.add_option("-f", "--credfile", help="The file path of your AWS credentials file.")

    # parse args
    options, _= parser.parse_args()

    # validate options
    if options.version:
        print(version)
        sys.exit(0)
    if not options.metricname:
        parser.error("Metric Name is required.")
        sys.exit(1)
    if not options.instanceid:
        parser.error("Instance ID is required.")
        sys.exit(1)
    if not options.statistics:
        options.statistics=["Average"]
        #[Minimum, Maximum, Average, SampleCount, Sum]
        #parser.error("At least one statistic is required.")
        # sys.exit(1)
    if not options.accesskeyid:
        options.accesskeyid = 'xxxxx'
        #parser.error("Access Key ID is required.")
        #sys.exit(1)
    if not options.secretaccesskey:
        options.secretaccesskey = 'xxxxx'
        #parser.error("Secret Access Key is required.")
        #sys.exit(1)
    if not options.region:
        parser.error("Region is required.")
        sys.exit(1)
    if not options.period:
        options.period = 60
        # parser.error("Period is required.")
        # sys.exit(1)

    return options


#==================================
#
# Initializes the cloudwatch client
#
# https://boto3.readthedocs.io/en/latest/reference/services/cloudwatch.html#client
#
#==================================

def initialize_client(options):
    
    client = boto3.client(
        'cloudwatch',
        aws_access_key_id = options.accesskeyid,
        aws_secret_access_key = options.secretaccesskey,
        region_name = options.region
    )

    return client

#==================================
#
# Get metrics statistics
#
# https://boto3.readthedocs.io/en/latest/reference/services/cloudwatch.html#CloudWatch.Client.get_metric_statistics
#
#==================================

def get_metrics(client, options):

    response = client.get_metric_statistics(
        Namespace = 'AWS/DynamoDB',
        Period = options.period,
        StartTime = datetime.now() - timedelta(seconds = 600),
        EndTime = datetime.now(),
        Dimensions=[
            {
                'Name': 'TableName',
                'Value': options.instanceid
            }   
        ],  
        MetricName = options.metricname,
        Statistics = options.statistics,
     
    )

    # Update our global response
    aws_response.update(response)

    return response



def get_return_code():

    warning = float(options.warning)
    critical = float(options.critical)

    try:
        datapoint = aws_response['Datapoints'][0]
        datapoint_key = options.statistics
        datapoint_key = datapoint_key[0]
        datapoint = datapoint[datapoint_key]

    except IndexError:
        print "No data in the current check period."
        exit(3);

    return_code = 0

    if datapoint > warning:
        return_code = 1
    if datapoint > critical:
        return_code = 2        
    if datapoint is None:
        return_code = 3

    return return_code   


def main():

    options = parse_options()    

    client = initialize_client(options)

    response = get_metrics(client, options)    

    get_return_code()

main()


metric_dictionary = {
    "ConditionalCheckFailedRequests" : "The number of failed attempts to perform conditional writes.",
    "ConsumedReadCapacityUnits" : "The number of read capacity units consumed over the specified time period.",
    "ConsumedWriteCapacityUnits" : "The number of write capacity units consumed over the specified time period.",
    "OnlineIndexConsumedWriteCapacity" : "The number of write capacity units consumed when adding a new global secondary index to a table.",
    "OnlineIndexPercentageProgress" : "The percentage of completion when a new global secondary index is being added to a table.",
    "OnlineIndexThrottleEvents" : "The number of write throttle events that occur when adding a new global secondary index to a table.",
    "PendingReplicationCount" : "The number of item updates that are written to one replica table, but that have not yet been written to another replica in the global table.",
    "ProvisionedReadCapacityUnits" : "The number of provisioned read capacity units for a table or a global secondary index.",
    "ProvisionedWriteCapacityUnits" : "The number of provisioned write capacity units for a table or a global secondary index.",
    "ReadThrottleEvents" : "Requests to DynamoDB that exceed the provisioned read capacity units for a table or a global secondary index.",
    "ReplicationLatency" : "The elapsed time between an updated item appearing in the DynamoDB stream for one replica table, and that item appearing in another replica in the global table.",
    "ReturnedBytes" : "The number of bytes returned by GetRecords operations during the specified time period.",
    "ReturnedItemCount" : "The number of items returned by Query or Scan operations during the specified time period.",
    "ReturnedRecordsCount" : "The number of stream records returned by GetRecords operations during the specified time period.",
    "SuccessfulRequestLatency" : "Successful requests to DynamoDB or Amazon DynamoDB Streams during the specified time period.",
    "SystemErrors" : "Requests to DynamoDB or Amazon DynamoDB Streams that generate an HTTP 500 status code during the specified time period.",
    "TimeToLiveDeletedItemCount" : "The number of items deleted by Time To Live (TTL) during the specified time period.",
    "ThrottledRequests" : "Requests to DynamoDB that exceed the provisioned throughput limits on a resource.",
    "UserErrors" : "Requests to DynamoDB or Amazon DynamoDB Streams that generate an HTTP 400 status code during the specified time period.",
    "WriteThrottleEvents" : "Requests to DynamoDB that exceed the provisioned write capacity units for a table or a global secondary index.",
}
