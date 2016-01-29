# Near RealTime ETL framework
The Near RealTime-ETL is a framework. With it Developers can create Near Real-time ETL applications which are the real programmes to extract,transform and load data. And this is the only right way to use the framework, because the framework itself doesn't provide any functional services.<br /><br />
The framework is designed to be able to extract-transform-load data in minute level, the most rapid cycle is one minute, which means it costs one minute from the generation of the data on servers until the data is ready to be used in the data warehouse.<br /><br />

# Data Flow
The applications of the Near Realtime ETL framework lay between the realtime pipe applications, such as Kafka, and the Data Warehouse constructed by HIVE and Hadoop. <br />
<ul>
<li>Step 1, the data from pipe as the source flow to the Near Realtime Apps</li>
<li>Step 2, the Near Realtime Apps transform the input data into the target format and store them on HDFS</li>
<li>Step 3, create partitions. At this moment, the data are ready for use.</li>
</ul>

# Design Thinking
<h3>Flexibility</h3>
Flexibility is put at the top priority in the design process. Because framework itself is a limit design. Everything is set within the framework so that the whole process can proceed well. But the new technologies keep on appearing in the world, so engineers have to break the limitations and upgrade some parts of the framework to adopt to the new requirements and challenges by integrating the new technologies.<br /><br />
So the design thinking is that one modul must has only one function. The whole framework is constructed with required moduls by a schedule programme. The schedule programme actually controls how the data flow through the moduls within the framework. If some modifications are needed because of upgrading or adjusting to new technologies, just add or modify relavent moduls. The apps of the framework can still run well with the new moduls.<br />
Currently, the framework has some moduls as bellow:<br /> 
<ul>
<li>Subscriber Modul, extract data from pipe.</li>
<li>Data Process Modul, process one piece of data.</li>
<li>Uploading Modul, upload data to HDFS</li>
<li>Partition Modul, update partition information.</li>
<li>Schedule Modul, as the main function programme organise all required modules</li>
<li>Log Modul, print logs.</li>
</ul>


