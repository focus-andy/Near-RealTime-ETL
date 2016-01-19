# Near RealTime ETL framework
The Near RealTime-ETL is a framework. With it Developers can create Real-time ETL applications which are the real ETL programmes running on servers. And this is the only right way to use the framework, because the framework itself doesn't provide any functional services.
The framework is designed to be able to extract-transform-load data in minute level, the most frequent cycle is one minute, which means it costs around one minute from the generation of the data on servers until it is ready to be used in the data warehouse. 

# Data Flow
The applications of the Near Realtime ETL framework lay between the realtime pipe applications, such as Kafka, and the Data Warehouse constructed by HIVE and Hadoop. <br />
First, the data from pipe as the source flow to the Near Realtime Apps<br />
Then, the Near Realtime Apps process the input data into the target format and store them on HDFS<br /> 
In the end, create partitions. At this moment, the data are ready for use.

# Design Thinking
1. Flexibility
Flexibility is put at the top priority in the design process. Because framework itself is a kind of limitation. Everything is set within the framework so that the whole process can run well. But the new technologies, as the new lucky dogs, always make us to break the limitations if we need to upgrade some parts of the framework.
So the design thinking is that one modul has only one function. The whole framework is constructed with required moduls by a schedule programme. The schedule programme actually controls how the data flow through the moduls within the framework. If some modifications are needed because of upgrading or adjusting to new technologies, just add or modify relavent moduls. The apps of the framework can still run well with the new moduls.<br />
Currently, the framework has some moduls as bellow:<br /> 
Subscriber Modul, extract data from pipe.
Data Process Modul, process one piece of data.
Uploading Modul, upload data to HDFS
Partition Modul, update partition information.
Schedule Modul, as the main function programme organise all required modules
Log Modul, print logs.



