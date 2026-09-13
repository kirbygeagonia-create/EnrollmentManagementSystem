-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: ems
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academicterms`
--

DROP TABLE IF EXISTS `academicterms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academicterms` (
  `termId` int NOT NULL AUTO_INCREMENT,
  `academicYearId` int NOT NULL,
  `semester` enum('1st','2nd','Summer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date NOT NULL,
  PRIMARY KEY (`termId`),
  KEY `fk_academicterms_academicyearid` (`academicYearId`),
  CONSTRAINT `academicterms_academicyearid_foreign` FOREIGN KEY (`academicYearId`) REFERENCES `academicyears` (`academicYearId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academicterms`
--

LOCK TABLES `academicterms` WRITE;
/*!40000 ALTER TABLE `academicterms` DISABLE KEYS */;
INSERT INTO `academicterms` VALUES (1,1,'1st','2024-06-01','2024-10-31'),(2,1,'2nd','2024-11-01','2025-03-31'),(3,1,'Summer','2025-04-01','2025-05-31'),(10,2,'1st','2025-06-01','2025-10-31'),(11,2,'2nd','2025-11-01','2026-03-31'),(18,2,'Summer','2026-04-01','2026-05-31');
/*!40000 ALTER TABLE `academicterms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academicunits`
--

DROP TABLE IF EXISTS `academicunits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academicunits` (
  `unitId` int NOT NULL AUTO_INCREMENT,
  `unitName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unitType` enum('college','department') COLLATE utf8mb4_unicode_ci NOT NULL,
  `parentUnitId` int DEFAULT NULL,
  PRIMARY KEY (`unitId`),
  KEY `fk_academicunits_parentunitid` (`parentUnitId`),
  CONSTRAINT `academicunits_parentunitid_foreign` FOREIGN KEY (`parentUnitId`) REFERENCES `academicunits` (`unitId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academicunits`
--

LOCK TABLES `academicunits` WRITE;
/*!40000 ALTER TABLE `academicunits` DISABLE KEYS */;
INSERT INTO `academicunits` VALUES (1,'College of Agriculture and Fisheries','college',NULL),(2,'College of Criminal Justice Education','college',NULL),(3,'College of Business and Governance','college',NULL),(4,'College of Information and Computing Technology','college',NULL),(5,'College of Engineering','college',NULL),(6,'College of Teacher Education','college',NULL);
/*!40000 ALTER TABLE `academicunits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academicyears`
--

DROP TABLE IF EXISTS `academicyears`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academicyears` (
  `academicYearId` int NOT NULL AUTO_INCREMENT,
  `yearLabel` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date NOT NULL,
  PRIMARY KEY (`academicYearId`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academicyears`
--

LOCK TABLES `academicyears` WRITE;
/*!40000 ALTER TABLE `academicyears` DISABLE KEYS */;
INSERT INTO `academicyears` VALUES (1,'2024-2025','2024-06-01','2025-05-31'),(2,'2025-2026','2025-06-01','2026-05-31');
/*!40000 ALTER TABLE `academicyears` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `addressId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `addressType` enum('home','current','permanent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `houseBuildingNo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sitioPurok` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cityMunicipality` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Philippines',
  `zipCode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`addressId`),
  KEY `fk_addresses_studentid` (`studentId`),
  CONSTRAINT `addresses_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
INSERT INTO `addresses` VALUES (105,53,'home','123','Rizal St','Purok 1','Barangay 1','Davao City','District 1','Davao del Sur','Region XI','Philippines','8000');
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admissionrequirements`
--

DROP TABLE IF EXISTS `admissionrequirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admissionrequirements` (
  `requirementId` int NOT NULL AUTO_INCREMENT,
  `requirementName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `appliesTo` enum('firstYear','transferee','shifter','continuing','all') COLLATE utf8mb4_unicode_ci NOT NULL,
  `isRequired` tinyint(1) NOT NULL,
  PRIMARY KEY (`requirementId`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admissionrequirements`
--

LOCK TABLES `admissionrequirements` WRITE;
/*!40000 ALTER TABLE `admissionrequirements` DISABLE KEYS */;
INSERT INTO `admissionrequirements` VALUES (1,'PSA Birth Certificate','firstYear',1),(2,'Form 138 / Report Card','firstYear',1),(3,'Transfer Credentials / Honorable Dismissal','transferee',1),(4,'Certificate of Good Moral Character','all',1);
/*!40000 ALTER TABLE `admissionrequirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admissions`
--

DROP TABLE IF EXISTS `admissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admissions` (
  `admissionId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `termId` int NOT NULL,
  `courseId` int NOT NULL,
  `applicantType` enum('firstYear','transferee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `applicationMode` enum('faceToFace','online') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'faceToFace',
  `admissionStatus` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `evaluatedBy` int DEFAULT NULL,
  `evaluatedDate` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`admissionId`),
  KEY `fk_admissions_studentid` (`studentId`),
  KEY `fk_admissions_termid` (`termId`),
  KEY `fk_admissions_courseid` (`courseId`),
  KEY `fk_admissions_evaluatedby` (`evaluatedBy`),
  CONSTRAINT `admissions_courseid_foreign` FOREIGN KEY (`courseId`) REFERENCES `courses` (`courseId`) ON UPDATE CASCADE,
  CONSTRAINT `admissions_evaluatedby_foreign` FOREIGN KEY (`evaluatedBy`) REFERENCES `staffusers` (`userId`) ON UPDATE CASCADE,
  CONSTRAINT `admissions_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE,
  CONSTRAINT `admissions_termid_foreign` FOREIGN KEY (`termId`) REFERENCES `academicterms` (`termId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admissions`
--

LOCK TABLES `admissions` WRITE;
/*!40000 ALTER TABLE `admissions` DISABLE KEYS */;
INSERT INTO `admissions` VALUES (27,53,18,3,'firstYear','faceToFace','approved',NULL,NULL,'2026-09-13 15:17:56','2026-09-13 15:17:56'),(28,55,18,3,'firstYear','faceToFace','pending',NULL,NULL,'2026-09-13 16:03:29','2026-09-13 16:03:29');
/*!40000 ALTER TABLE `admissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditlogs`
--

DROP TABLE IF EXISTS `auditlogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditlogs` (
  `auditId` int NOT NULL AUTO_INCREMENT,
  `userId` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entityTable` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entityId` int NOT NULL,
  `oldValues` json DEFAULT NULL,
  `newValues` json DEFAULT NULL,
  `ipAddress` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`auditId`),
  KEY `fk_auditlogs_user` (`userId`),
  CONSTRAINT `auditlogs_userid_foreign` FOREIGN KEY (`userId`) REFERENCES `staffusers` (`userId`)
) ENGINE=InnoDB AUTO_INCREMENT=8246 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditlogs`
--

LOCK TABLES `auditlogs` WRITE;
/*!40000 ALTER TABLE `auditlogs` DISABLE KEYS */;
INSERT INTO `auditlogs` VALUES (1,NULL,'created','offices',1,NULL,'\"{\\\"officeId\\\":1,\\\"officeName\\\":\\\"Registrar\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(2,NULL,'created','offices',2,NULL,'\"{\\\"officeId\\\":2,\\\"officeName\\\":\\\"Accounting\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(3,NULL,'created','offices',3,NULL,'\"{\\\"officeId\\\":3,\\\"officeName\\\":\\\"Scholarship\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(4,NULL,'created','offices',4,NULL,'\"{\\\"officeId\\\":4,\\\"officeName\\\":\\\"Guidance\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(5,NULL,'created','offices',5,NULL,'\"{\\\"officeId\\\":5,\\\"officeName\\\":\\\"Blocking\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(6,NULL,'created','offices',6,NULL,'\"{\\\"officeId\\\":6,\\\"officeName\\\":\\\"Admission\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(7,NULL,'created','offices',7,NULL,'\"{\\\"officeId\\\":7,\\\"officeName\\\":\\\"Academic Department\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(8,NULL,'created','offices',8,NULL,'\"{\\\"officeId\\\":8,\\\"officeName\\\":\\\"Clearance\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(9,NULL,'created','offices',11,NULL,'\"{\\\"officeId\\\":11,\\\"officeName\\\":\\\"Clinic\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(10,NULL,'created','offices',22,NULL,'\"{\\\"officeId\\\":22,\\\"officeName\\\":\\\"ID Office\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(11,NULL,'created','academicunits',1,NULL,'\"{\\\"unitId\\\":1,\\\"unitName\\\":\\\"College of Agriculture and Fisheries\\\",\\\"unitType\\\":\\\"college\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(12,NULL,'created','academicunits',2,NULL,'\"{\\\"unitId\\\":2,\\\"unitName\\\":\\\"College of Criminal Justice Education\\\",\\\"unitType\\\":\\\"college\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(13,NULL,'created','academicunits',3,NULL,'\"{\\\"unitId\\\":3,\\\"unitName\\\":\\\"College of Business and Governance\\\",\\\"unitType\\\":\\\"college\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(14,NULL,'created','academicunits',4,NULL,'\"{\\\"unitId\\\":4,\\\"unitName\\\":\\\"College of Information and Computing Technology\\\",\\\"unitType\\\":\\\"college\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(15,NULL,'created','academicunits',5,NULL,'\"{\\\"unitId\\\":5,\\\"unitName\\\":\\\"College of Engineering\\\",\\\"unitType\\\":\\\"college\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(16,NULL,'created','academicunits',6,NULL,'\"{\\\"unitId\\\":6,\\\"unitName\\\":\\\"College of Teacher Education\\\",\\\"unitType\\\":\\\"college\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(17,NULL,'created','religions',1,NULL,'\"{\\\"religionId\\\":1,\\\"religionName\\\":\\\"Roman Catholic\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(18,NULL,'created','academicyears',1,NULL,'\"{\\\"yearLabel\\\":\\\"2024-2025\\\",\\\"startDate\\\":\\\"2024-06-01 00:00:00\\\",\\\"endDate\\\":\\\"2025-05-31 00:00:00\\\",\\\"academicYearId\\\":1}\"','127.0.0.1','2026-08-30 06:56:20'),(19,NULL,'created','academicyears',2,NULL,'\"{\\\"yearLabel\\\":\\\"2025-2026\\\",\\\"startDate\\\":\\\"2025-06-01 00:00:00\\\",\\\"endDate\\\":\\\"2026-05-31 00:00:00\\\",\\\"academicYearId\\\":2}\"','127.0.0.1','2026-08-30 06:56:20'),(20,NULL,'created','academicterms',1,NULL,'\"{\\\"termId\\\":1,\\\"academicYearId\\\":1,\\\"semester\\\":\\\"1st\\\",\\\"startDate\\\":\\\"2024-06-01 00:00:00\\\",\\\"endDate\\\":\\\"2024-10-31 00:00:00\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(21,NULL,'created','academicterms',2,NULL,'\"{\\\"termId\\\":2,\\\"academicYearId\\\":1,\\\"semester\\\":\\\"2nd\\\",\\\"startDate\\\":\\\"2024-11-01 00:00:00\\\",\\\"endDate\\\":\\\"2025-03-31 00:00:00\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(22,NULL,'created','academicterms',3,NULL,'\"{\\\"termId\\\":3,\\\"academicYearId\\\":1,\\\"semester\\\":\\\"Summer\\\",\\\"startDate\\\":\\\"2025-04-01 00:00:00\\\",\\\"endDate\\\":\\\"2025-05-31 00:00:00\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(23,NULL,'created','academicterms',10,NULL,'\"{\\\"termId\\\":10,\\\"academicYearId\\\":2,\\\"semester\\\":\\\"1st\\\",\\\"startDate\\\":\\\"2025-06-01 00:00:00\\\",\\\"endDate\\\":\\\"2025-10-31 00:00:00\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(24,NULL,'created','academicterms',11,NULL,'\"{\\\"termId\\\":11,\\\"academicYearId\\\":2,\\\"semester\\\":\\\"2nd\\\",\\\"startDate\\\":\\\"2025-11-01 00:00:00\\\",\\\"endDate\\\":\\\"2026-03-31 00:00:00\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(25,NULL,'created','academicterms',18,NULL,'\"{\\\"termId\\\":18,\\\"academicYearId\\\":2,\\\"semester\\\":\\\"Summer\\\",\\\"startDate\\\":\\\"2026-04-01 00:00:00\\\",\\\"endDate\\\":\\\"2026-05-31 00:00:00\\\"}\"','127.0.0.1','2026-08-30 06:56:20'),(26,NULL,'created','clearanceperiods',1,NULL,'\"{\\\"termId\\\":18,\\\"clearanceStartDate\\\":\\\"2026-04-01 00:00:00\\\",\\\"clearanceEndDate\\\":\\\"2026-05-31 00:00:00\\\",\\\"periodStatus\\\":\\\"open\\\",\\\"clearancePeriodId\\\":1}\"','127.0.0.1','2026-08-30 06:56:20'),(27,NULL,'created','courses',1,NULL,'\"{\\\"courseId\\\":1,\\\"unitId\\\":4,\\\"courseCode\\\":\\\"BSIT\\\",\\\"courseName\\\":\\\"Bachelor of Science in Information Technology\\\",\\\"requiresEntranceExam\\\":false,\\\"requiresRetentionExam\\\":false}\"','127.0.0.1','2026-08-30 06:56:20'),(28,NULL,'created','courses',2,NULL,'\"{\\\"courseId\\\":2,\\\"unitId\\\":4,\\\"courseCode\\\":\\\"BSCS\\\",\\\"courseName\\\":\\\"Bachelor of Science in Computer Science\\\",\\\"requiresEntranceExam\\\":false,\\\"requiresRetentionExam\\\":false}\"','127.0.0.1','2026-08-30 06:56:20'),(29,NULL,'created','courses',3,NULL,'\"{\\\"courseId\\\":3,\\\"unitId\\\":2,\\\"courseCode\\\":\\\"BSCrim\\\",\\\"courseName\\\":\\\"Bachelor of Science in Criminology\\\",\\\"requiresEntranceExam\\\":true,\\\"requiresRetentionExam\\\":false}\"','127.0.0.1','2026-08-30 06:56:20'),(30,NULL,'created','courses',4,NULL,'\"{\\\"courseId\\\":4,\\\"unitId\\\":1,\\\"courseCode\\\":\\\"BSA\\\",\\\"courseName\\\":\\\"Bachelor of Science in Agriculture\\\",\\\"requiresEntranceExam\\\":false,\\\"requiresRetentionExam\\\":false}\"','127.0.0.1','2026-08-30 06:56:20'),(31,NULL,'created','courses',5,NULL,'\"{\\\"courseId\\\":5,\\\"unitId\\\":3,\\\"courseCode\\\":\\\"BSBA\\\",\\\"courseName\\\":\\\"Bachelor of Science in Business Administration\\\",\\\"requiresEntranceExam\\\":false,\\\"requiresRetentionExam\\\":false}\"','127.0.0.1','2026-08-30 06:56:20'),(32,NULL,'created','courses',6,NULL,'\"{\\\"courseId\\\":6,\\\"unitId\\\":6,\\\"courseCode\\\":\\\"BSEd\\\",\\\"courseName\\\":\\\"Bachelor of Secondary Education\\\",\\\"requiresEntranceExam\\\":false,\\\"requiresRetentionExam\\\":false}\"','127.0.0.1','2026-08-30 06:56:20'),(33,NULL,'created','subjects',1,NULL,'\"{\\\"subjectCode\\\":\\\"GEN101\\\",\\\"subjectName\\\":\\\"General Education 1\\\",\\\"subjectType\\\":\\\"lecture\\\",\\\"lectureUnits\\\":3,\\\"labUnits\\\":0,\\\"subjectId\\\":1}\"','127.0.0.1','2026-08-30 06:56:20'),(34,NULL,'created','subjects',2,NULL,'\"{\\\"subjectCode\\\":\\\"ENG101\\\",\\\"subjectName\\\":\\\"English 1\\\",\\\"subjectType\\\":\\\"lecture\\\",\\\"lectureUnits\\\":3,\\\"labUnits\\\":0,\\\"subjectId\\\":2}\"','127.0.0.1','2026-08-30 06:56:20'),(35,NULL,'created','subjects',3,NULL,'\"{\\\"subjectCode\\\":\\\"MATH101\\\",\\\"subjectName\\\":\\\"Mathematics 1\\\",\\\"subjectType\\\":\\\"lecture\\\",\\\"lectureUnits\\\":3,\\\"labUnits\\\":0,\\\"subjectId\\\":3}\"','127.0.0.1','2026-08-30 06:56:20'),(36,NULL,'created','subjects',4,NULL,'\"{\\\"subjectCode\\\":\\\"CRIM101\\\",\\\"subjectName\\\":\\\"Introduction to Criminology\\\",\\\"subjectType\\\":\\\"lecture\\\",\\\"lectureUnits\\\":3,\\\"labUnits\\\":0,\\\"subjectId\\\":4}\"','127.0.0.1','2026-08-30 06:56:20'),(37,NULL,'created','subjects',5,NULL,'\"{\\\"subjectCode\\\":\\\"IT101\\\",\\\"subjectName\\\":\\\"Introduction to Computing\\\",\\\"subjectType\\\":\\\"lecture\\\",\\\"lectureUnits\\\":3,\\\"labUnits\\\":0,\\\"subjectId\\\":5}\"','127.0.0.1','2026-08-30 06:56:20'),(38,NULL,'created','subjects',6,NULL,'\"{\\\"subjectCode\\\":\\\"COM101\\\",\\\"subjectName\\\":\\\"Communication Skills\\\",\\\"subjectType\\\":\\\"lecture\\\",\\\"lectureUnits\\\":3,\\\"labUnits\\\":0,\\\"subjectId\\\":6}\"','127.0.0.1','2026-08-30 06:56:20'),(39,NULL,'created','rooms',1,NULL,'\"{\\\"roomName\\\":\\\"Room 101\\\",\\\"capacity\\\":40,\\\"building\\\":\\\"Main Building\\\",\\\"roomId\\\":1}\"','127.0.0.1','2026-08-30 06:56:20'),(40,NULL,'created','feetypes',1,NULL,'\"{\\\"feeName\\\":\\\"Tuition Fee (per unit)\\\",\\\"defaultAmount\\\":1250,\\\"unitBasis\\\":\\\"perUnit\\\",\\\"feeTypeId\\\":1}\"','127.0.0.1','2026-08-30 06:56:20'),(41,NULL,'created','feetypes',2,NULL,'\"{\\\"feeName\\\":\\\"Miscellaneous Fee\\\",\\\"defaultAmount\\\":1500,\\\"unitBasis\\\":\\\"flat\\\",\\\"feeTypeId\\\":2}\"','127.0.0.1','2026-08-30 06:56:20'),(42,NULL,'created','feetypes',3,NULL,'\"{\\\"feeName\\\":\\\"Laboratory Fee\\\",\\\"defaultAmount\\\":500,\\\"unitBasis\\\":\\\"perUnit\\\",\\\"feeTypeId\\\":3}\"','127.0.0.1','2026-08-30 06:56:20'),(43,NULL,'created','feetypes',4,NULL,'\"{\\\"feeName\\\":\\\"Library Fee\\\",\\\"defaultAmount\\\":250,\\\"unitBasis\\\":\\\"flat\\\",\\\"feeTypeId\\\":4}\"','127.0.0.1','2026-08-30 06:56:20'),(44,NULL,'created','admissionrequirements',1,NULL,'\"{\\\"requirementName\\\":\\\"PSA Birth Certificate\\\",\\\"appliesTo\\\":\\\"firstYear\\\",\\\"isRequired\\\":true,\\\"requirementId\\\":1}\"','127.0.0.1','2026-08-30 06:56:20'),(45,NULL,'created','admissionrequirements',2,NULL,'\"{\\\"requirementName\\\":\\\"Form 138 \\\\/ Report Card\\\",\\\"appliesTo\\\":\\\"firstYear\\\",\\\"isRequired\\\":true,\\\"requirementId\\\":2}\"','127.0.0.1','2026-08-30 06:56:20'),(46,NULL,'created','admissionrequirements',3,NULL,'\"{\\\"requirementName\\\":\\\"Transfer Credentials \\\\/ Honorable Dismissal\\\",\\\"appliesTo\\\":\\\"transferee\\\",\\\"isRequired\\\":true,\\\"requirementId\\\":3}\"','127.0.0.1','2026-08-30 06:56:20'),(47,NULL,'created','admissionrequirements',4,NULL,'\"{\\\"requirementName\\\":\\\"Certificate of Good Moral Character\\\",\\\"appliesTo\\\":\\\"all\\\",\\\"isRequired\\\":true,\\\"requirementId\\\":4}\"','127.0.0.1','2026-08-30 06:56:20'),(48,NULL,'created','clearancerequirements',1,NULL,'\"{\\\"officeId\\\":1,\\\"clearanceRequirementId\\\":1}\"','127.0.0.1','2026-08-30 06:56:20'),(49,NULL,'created','clearancerequirements',2,NULL,'\"{\\\"officeId\\\":2,\\\"clearanceRequirementId\\\":2}\"','127.0.0.1','2026-08-30 06:56:20'),(50,NULL,'created','clearancerequirements',3,NULL,'\"{\\\"officeId\\\":3,\\\"clearanceRequirementId\\\":3}\"','127.0.0.1','2026-08-30 06:56:20'),(51,NULL,'created','clearancerequirements',4,NULL,'\"{\\\"officeId\\\":4,\\\"clearanceRequirementId\\\":4}\"','127.0.0.1','2026-08-30 06:56:20'),(52,NULL,'created','clearancerequirements',5,NULL,'\"{\\\"officeId\\\":5,\\\"clearanceRequirementId\\\":5}\"','127.0.0.1','2026-08-30 06:56:20'),(53,NULL,'created','clearancerequirements',6,NULL,'\"{\\\"officeId\\\":6,\\\"clearanceRequirementId\\\":6}\"','127.0.0.1','2026-08-30 06:56:20'),(54,NULL,'created','clearancerequirements',7,NULL,'\"{\\\"officeId\\\":7,\\\"clearanceRequirementId\\\":7}\"','127.0.0.1','2026-08-30 06:56:20'),(55,NULL,'created','clearancerequirements',8,NULL,'\"{\\\"officeId\\\":8,\\\"clearanceRequirementId\\\":8}\"','127.0.0.1','2026-08-30 06:56:20'),(56,NULL,'created','clearancerequirements',9,NULL,'\"{\\\"officeId\\\":11,\\\"clearanceRequirementId\\\":9}\"','127.0.0.1','2026-08-30 06:56:20'),(57,NULL,'created','clearancerequirements',10,NULL,'\"{\\\"officeId\\\":22,\\\"clearanceRequirementId\\\":10}\"','127.0.0.1','2026-08-30 06:56:20'),(58,NULL,'created','staffusers',1,NULL,'\"{\\\"username\\\":\\\"staff8\\\",\\\"employeeNo\\\":\\\"EMP-00008\\\",\\\"firstName\\\":\\\"System\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Administrator\\\",\\\"email\\\":\\\"staff8@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$0Kx9RScvIxBSQwvYTeQ\\\\/3eG0VCs0NKtm.D8HO2Li20wM5mcN9Q2Aq\\\",\\\"officeId\\\":1,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"admin\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":1}\"','127.0.0.1','2026-08-30 06:56:21'),(59,NULL,'created','staffusers',2,NULL,'\"{\\\"username\\\":\\\"office1_head\\\",\\\"employeeNo\\\":\\\"EMP-00101\\\",\\\"firstName\\\":\\\"Registrar Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office1_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$2KGVszPTO7TZVVdLdMA1lu\\\\/tRPbJuLtvgIsVRwqaQ9nVbYLI8JBnS\\\",\\\"officeId\\\":1,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":2}\"','127.0.0.1','2026-08-30 06:56:22'),(60,NULL,'created','staffusers',3,NULL,'\"{\\\"username\\\":\\\"office2_head\\\",\\\"employeeNo\\\":\\\"EMP-00102\\\",\\\"firstName\\\":\\\"Accounting Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office2_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$j0u\\\\/ojb1Au7jASk43IQyzOtMaV9clLmlSzApgRNYt8k8E3FSmYBHK\\\",\\\"officeId\\\":2,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":3}\"','127.0.0.1','2026-08-30 06:56:22'),(61,NULL,'created','staffusers',4,NULL,'\"{\\\"username\\\":\\\"office3_head\\\",\\\"employeeNo\\\":\\\"EMP-00103\\\",\\\"firstName\\\":\\\"Scholarship Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office3_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$4V5CuKBmgRm7ZBpwy4TxUe\\\\/ZtoaROZw1CiQGf85Q6Vu\\\\/eQug4Xwem\\\",\\\"officeId\\\":3,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":4}\"','127.0.0.1','2026-08-30 06:56:23'),(62,NULL,'created','staffusers',5,NULL,'\"{\\\"username\\\":\\\"office4_head\\\",\\\"employeeNo\\\":\\\"EMP-00104\\\",\\\"firstName\\\":\\\"Guidance Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office4_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$aQkiBZxkjn\\\\/I58ERyeWqzuEwP5LWB9NbvTeBrXdg1LumMQccwPSBS\\\",\\\"officeId\\\":4,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":5}\"','127.0.0.1','2026-08-30 06:56:23'),(63,NULL,'created','staffusers',6,NULL,'\"{\\\"username\\\":\\\"office5_head\\\",\\\"employeeNo\\\":\\\"EMP-00105\\\",\\\"firstName\\\":\\\"Blocking Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office5_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$4qOkrPUtzSQpTJnqJ1fsCe4FCulfvT20F1cu0VzRHNGSVGn15AJeS\\\",\\\"officeId\\\":5,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":6}\"','127.0.0.1','2026-08-30 06:56:23'),(64,NULL,'created','staffusers',7,NULL,'\"{\\\"username\\\":\\\"office6_head\\\",\\\"employeeNo\\\":\\\"EMP-00106\\\",\\\"firstName\\\":\\\"Admission Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office6_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$hpq5oq8J4pVGnhM9P.\\\\/SUOpoOTBWUArk2o5vmSka62TJt5R.jZzVC\\\",\\\"officeId\\\":6,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":7}\"','127.0.0.1','2026-08-30 06:56:24'),(65,NULL,'created','staffusers',8,NULL,'\"{\\\"username\\\":\\\"office7_head\\\",\\\"employeeNo\\\":\\\"EMP-00107\\\",\\\"firstName\\\":\\\"Academic Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office7_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$ZbBuDah5Ov72cnVIvvYSi.2z\\\\/lu0\\\\/V8usNYobrGd88mN0NumUURE2\\\",\\\"officeId\\\":7,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":8}\"','127.0.0.1','2026-08-30 06:56:24'),(66,NULL,'created','staffusers',9,NULL,'\"{\\\"username\\\":\\\"office8_head\\\",\\\"employeeNo\\\":\\\"EMP-00108\\\",\\\"firstName\\\":\\\"Clearance Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office8_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$pNaEaES\\\\/LpCWCDzgqoSz1uQsbbKya3AWmy972aJFr.SK\\\\/3vncHmHC\\\",\\\"officeId\\\":8,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":9}\"','127.0.0.1','2026-08-30 06:56:25'),(67,NULL,'created','staffusers',10,NULL,'\"{\\\"username\\\":\\\"office11_head\\\",\\\"employeeNo\\\":\\\"EMP-00109\\\",\\\"firstName\\\":\\\"Clinic Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office11_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$rJ2F3fX9ZLAq25okdD8ZguBAxL97gKI5MkiBmrcNB8Kc7KhCyeYdK\\\",\\\"officeId\\\":11,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":10}\"','127.0.0.1','2026-08-30 06:56:25'),(68,NULL,'created','staffusers',11,NULL,'\"{\\\"username\\\":\\\"office22_head\\\",\\\"employeeNo\\\":\\\"EMP-00110\\\",\\\"firstName\\\":\\\"ID Head\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Staff\\\",\\\"email\\\":\\\"office22_head@seait.edu.ph\\\",\\\"passwordHash\\\":\\\"$2y$12$KrAwNGX\\\\/KNzJf3KhUUZROu2\\\\/BAkAlSpKiS2gQK74TZcgnyzOQaXuq\\\",\\\"officeId\\\":22,\\\"contactNo\\\":\\\"\\\",\\\"role\\\":\\\"officeHead\\\",\\\"status\\\":\\\"active\\\",\\\"userId\\\":11}\"','127.0.0.1','2026-08-30 06:56:26'),(3044,NULL,'updated','staffusers',1,'\"{\\\"userId\\\":1,\\\"officeId\\\":1,\\\"unitId\\\":null,\\\"employeeNo\\\":\\\"EMP-00008\\\",\\\"firstName\\\":\\\"System\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Administrator\\\",\\\"username\\\":\\\"staff8\\\",\\\"passwordHash\\\":\\\"[REDACTED]\\\",\\\"remember_token\\\":\\\"[REDACTED]\\\",\\\"role\\\":\\\"admin\\\",\\\"email\\\":\\\"staff8@seait.edu.ph\\\",\\\"contactNo\\\":\\\"\\\",\\\"status\\\":\\\"active\\\"}\"','\"{\\\"userId\\\":1,\\\"officeId\\\":1,\\\"unitId\\\":null,\\\"employeeNo\\\":\\\"EMP-00008\\\",\\\"firstName\\\":\\\"System\\\",\\\"middleName\\\":\\\"\\\",\\\"lastName\\\":\\\"Administrator\\\",\\\"username\\\":\\\"staff8\\\",\\\"passwordHash\\\":\\\"[REDACTED]\\\",\\\"remember_token\\\":\\\"[REDACTED]\\\",\\\"role\\\":\\\"admin\\\",\\\"email\\\":\\\"staff8@seait.edu.ph\\\",\\\"contactNo\\\":\\\"\\\",\\\"status\\\":\\\"active\\\"}\"','127.0.0.1','2026-09-05 13:28:51'),(5595,7,'created','students',53,NULL,'\"{\\\"schoolIdNumber\\\":\\\"DEMO-2026-001\\\",\\\"lastName\\\":\\\"Dela Cruz\\\",\\\"firstName\\\":\\\"Juan\\\",\\\"middleName\\\":\\\"P\\\",\\\"suffix\\\":\\\"N\\\\/A\\\",\\\"gender\\\":\\\"male\\\",\\\"birthdate\\\":\\\"2004-01-01 00:00:00\\\",\\\"birthplace\\\":\\\"Test City\\\",\\\"citizenship\\\":\\\"Filipino\\\",\\\"religionId\\\":1,\\\"civilStatus\\\":\\\"single\\\",\\\"contactNumber\\\":\\\"09171234567\\\",\\\"telephoneNumber\\\":null,\\\"email\\\":\\\"demo.juan@example.com\\\",\\\"username\\\":\\\"demo_juan\\\",\\\"passwordHash\\\":\\\"[REDACTED]\\\",\\\"status\\\":\\\"active\\\",\\\"semestersCompleted\\\":0,\\\"yearsInInstitution\\\":0,\\\"updated_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"studentId\\\":53}\"','127.0.0.1','2026-09-12 17:17:56'),(5596,7,'created','addresses',105,NULL,'\"{\\\"addressType\\\":\\\"home\\\",\\\"houseBuildingNo\\\":\\\"123\\\",\\\"street\\\":\\\"Rizal St\\\",\\\"sitioPurok\\\":\\\"Purok 1\\\",\\\"barangay\\\":\\\"Barangay 1\\\",\\\"cityMunicipality\\\":\\\"Davao City\\\",\\\"district\\\":\\\"District 1\\\",\\\"province\\\":\\\"Davao del Sur\\\",\\\"region\\\":\\\"Region XI\\\",\\\"zipCode\\\":\\\"8000\\\",\\\"country\\\":\\\"Philippines\\\",\\\"studentId\\\":53,\\\"addressId\\\":105}\"','127.0.0.1','2026-09-12 17:17:56'),(5597,7,'created','guardians',79,NULL,'\"{\\\"relationship\\\":\\\"mother\\\",\\\"fullName\\\":\\\"Maria Dela Cruz\\\",\\\"contactNumber\\\":\\\"09171234568\\\",\\\"email\\\":\\\"demo.maria@example.com\\\",\\\"isEmergencyContact\\\":true,\\\"isAuthorizedToActOnBehalf\\\":true,\\\"studentId\\\":53,\\\"guardianId\\\":79}\"','127.0.0.1','2026-09-12 17:17:56'),(5598,7,'created','educationalinstitutions',53,NULL,'\"{\\\"institutionName\\\":\\\"Test National High School\\\",\\\"institutionType\\\":\\\"seniorHigh\\\",\\\"cityMunicipality\\\":\\\"Davao City\\\",\\\"province\\\":\\\"Davao del Sur\\\",\\\"institutionId\\\":53}\"','127.0.0.1','2026-09-12 17:17:56'),(5599,7,'created','studenteducationalbackgrounds',27,NULL,'\"{\\\"studentId\\\":53,\\\"institutionId\\\":53,\\\"levelCompleted\\\":\\\"seniorHigh\\\",\\\"strandTrack\\\":\\\"STEM\\\",\\\"yearCompleted\\\":\\\"2024-03-31 00:00:00\\\",\\\"honorsCertifications\\\":\\\"\\\",\\\"supportingDocumentPath\\\":\\\"\\\",\\\"backgroundId\\\":27}\"','127.0.0.1','2026-09-12 17:17:56'),(5600,7,'created','admissions',27,NULL,'\"{\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"termId\\\":18,\\\"applicantType\\\":\\\"firstYear\\\",\\\"admissionStatus\\\":\\\"pending\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"admissionId\\\":27}\"','127.0.0.1','2026-09-12 17:17:56'),(5601,7,'created','studentrequirementsubmissions',66,NULL,'\"{\\\"admissionId\\\":27,\\\"requirementId\\\":1,\\\"submissionStatus\\\":\\\"pending\\\",\\\"submittedDate\\\":\\\"2026-09-13 04:17:56\\\",\\\"remarks\\\":\\\"\\\",\\\"submissionId\\\":66}\"','127.0.0.1','2026-09-12 17:17:56'),(5602,7,'created','studentrequirementsubmissions',67,NULL,'\"{\\\"admissionId\\\":27,\\\"requirementId\\\":2,\\\"submissionStatus\\\":\\\"pending\\\",\\\"submittedDate\\\":\\\"2026-09-13 04:17:56\\\",\\\"remarks\\\":\\\"\\\",\\\"submissionId\\\":67}\"','127.0.0.1','2026-09-12 17:17:56'),(5603,7,'created','studentrequirementsubmissions',68,NULL,'\"{\\\"admissionId\\\":27,\\\"requirementId\\\":4,\\\"submissionStatus\\\":\\\"pending\\\",\\\"submittedDate\\\":\\\"2026-09-13 04:17:56\\\",\\\"remarks\\\":\\\"\\\",\\\"submissionId\\\":68}\"','127.0.0.1','2026-09-12 17:17:56'),(5604,8,'created','examresults',27,NULL,'\"{\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"termId\\\":18,\\\"examStage\\\":\\\"entrance\\\",\\\"examType\\\":\\\"general\\\",\\\"examResult\\\":\\\"pass\\\",\\\"examDate\\\":\\\"2026-09-13 00:00:00\\\",\\\"examId\\\":27}\"','127.0.0.1','2026-09-12 17:17:56'),(5605,5,'created','examresults',28,NULL,'\"{\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"termId\\\":18,\\\"examStage\\\":\\\"entrance\\\",\\\"examType\\\":\\\"courseSpecific\\\",\\\"examResult\\\":\\\"pass\\\",\\\"examDate\\\":\\\"2026-09-13 00:00:00\\\",\\\"examId\\\":28}\"','127.0.0.1','2026-09-12 17:17:56'),(5606,5,'updated','admissions',27,'\"{\\\"admissionId\\\":27,\\\"studentId\\\":53,\\\"termId\\\":18,\\\"courseId\\\":3,\\\"applicantType\\\":\\\"firstYear\\\",\\\"applicationMode\\\":\\\"faceToFace\\\",\\\"admissionStatus\\\":\\\"pending\\\",\\\"evaluatedBy\\\":null,\\\"evaluatedDate\\\":null,\\\"created_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\"}\"','\"{\\\"admissionId\\\":27,\\\"studentId\\\":53,\\\"termId\\\":18,\\\"courseId\\\":3,\\\"applicantType\\\":\\\"firstYear\\\",\\\"applicationMode\\\":\\\"faceToFace\\\",\\\"admissionStatus\\\":\\\"approved\\\",\\\"evaluatedBy\\\":null,\\\"evaluatedDate\\\":null,\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:56\\\"}\"','127.0.0.1','2026-09-12 17:17:56'),(5607,NULL,'created','enrollments',53,NULL,'\"{\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"termId\\\":18,\\\"admissionId\\\":27,\\\"yearLevel\\\":1,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"evaluatedBy\\\":5,\\\"enrollmentStatus\\\":\\\"pending\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"enrollmentId\\\":53}\"','127.0.0.1','2026-09-12 17:17:56'),(5608,5,'created','enrolledsubjects',157,NULL,'\"{\\\"enrollmentId\\\":53,\\\"subjectId\\\":1,\\\"status\\\":\\\"proposed\\\",\\\"attempt_number\\\":1,\\\"original_enrolled_subject_id\\\":null,\\\"enrolledSubjectId\\\":157}\"','127.0.0.1','2026-09-12 17:17:57'),(5609,5,'created','enrolledsubjects',158,NULL,'\"{\\\"enrollmentId\\\":53,\\\"subjectId\\\":2,\\\"status\\\":\\\"proposed\\\",\\\"attempt_number\\\":1,\\\"original_enrolled_subject_id\\\":null,\\\"enrolledSubjectId\\\":158}\"','127.0.0.1','2026-09-12 17:17:57'),(5610,5,'created','enrolledsubjects',159,NULL,'\"{\\\"enrollmentId\\\":53,\\\"subjectId\\\":3,\\\"status\\\":\\\"proposed\\\",\\\"attempt_number\\\":1,\\\"original_enrolled_subject_id\\\":null,\\\"enrolledSubjectId\\\":159}\"','127.0.0.1','2026-09-12 17:17:57'),(5611,5,'updated','enrollments',53,'\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"pending\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":null,\\\"created_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\"}\"','\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"evaluated\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":null,\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5612,5,'created','enrollmentstatushistory',209,NULL,'\"{\\\"enrollmentId\\\":53,\\\"fromStatus\\\":\\\"pending\\\",\\\"toStatus\\\":\\\"evaluated\\\",\\\"changedBy\\\":5,\\\"remarks\\\":\\\"Subject load proposed by evaluator\\\",\\\"changedAt\\\":\\\"2026-09-13 04:17:57\\\",\\\"historyId\\\":209}\"','127.0.0.1','2026-09-12 17:17:57'),(5613,5,'updated','enrollments',53,'\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"evaluated\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":null,\\\"created_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\"}\"','\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"evaluated\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13 04:17:57\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5614,5,'created','enrollmentworkflow',53,NULL,'\"{\\\"enrollmentId\\\":53,\\\"currentStep\\\":1,\\\"workflowStatus\\\":\\\"inProgress\\\",\\\"workflowId\\\":53}\"','127.0.0.1','2026-09-12 17:17:57'),(5615,5,'created','workflowsteps',339,NULL,'\"{\\\"workflowId\\\":53,\\\"officeId\\\":4,\\\"stepOrder\\\":1,\\\"stepStatus\\\":\\\"pending\\\",\\\"workflowStepId\\\":339}\"','127.0.0.1','2026-09-12 17:17:57'),(5616,5,'created','workflowsteps',340,NULL,'\"{\\\"workflowId\\\":53,\\\"officeId\\\":3,\\\"stepOrder\\\":2,\\\"stepStatus\\\":\\\"pending\\\",\\\"workflowStepId\\\":340}\"','127.0.0.1','2026-09-12 17:17:57'),(5617,5,'created','workflowsteps',341,NULL,'\"{\\\"workflowId\\\":53,\\\"officeId\\\":2,\\\"stepOrder\\\":3,\\\"stepStatus\\\":\\\"pending\\\",\\\"workflowStepId\\\":341}\"','127.0.0.1','2026-09-12 17:17:57'),(5618,5,'created','workflowsteps',342,NULL,'\"{\\\"workflowId\\\":53,\\\"officeId\\\":1,\\\"stepOrder\\\":4,\\\"stepStatus\\\":\\\"pending\\\",\\\"workflowStepId\\\":342}\"','127.0.0.1','2026-09-12 17:17:57'),(5619,5,'created','workflowsteps',343,NULL,'\"{\\\"workflowId\\\":53,\\\"officeId\\\":5,\\\"stepOrder\\\":5,\\\"stepStatus\\\":\\\"pending\\\",\\\"workflowStepId\\\":343}\"','127.0.0.1','2026-09-12 17:17:57'),(5620,5,'created','workflowsteps',344,NULL,'\"{\\\"workflowId\\\":53,\\\"officeId\\\":11,\\\"stepOrder\\\":6,\\\"stepStatus\\\":\\\"pending\\\",\\\"workflowStepId\\\":344}\"','127.0.0.1','2026-09-12 17:17:57'),(5621,5,'created','workflowsteps',345,NULL,'\"{\\\"workflowId\\\":53,\\\"officeId\\\":22,\\\"stepOrder\\\":7,\\\"stepStatus\\\":\\\"pending\\\",\\\"workflowStepId\\\":345}\"','127.0.0.1','2026-09-12 17:17:57'),(5622,5,'updated','workflowsteps',339,'\"{\\\"workflowStepId\\\":339,\\\"workflowId\\\":53,\\\"officeId\\\":4,\\\"stepOrder\\\":1,\\\"stepStatus\\\":\\\"pending\\\",\\\"signedBy\\\":null,\\\"signedDate\\\":null}\"','\"{\\\"workflowStepId\\\":339,\\\"workflowId\\\":53,\\\"officeId\\\":4,\\\"stepOrder\\\":1,\\\"stepStatus\\\":\\\"completed\\\",\\\"signedBy\\\":5,\\\"signedDate\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5623,4,'created','studentassessments',53,NULL,'\"{\\\"enrollmentId\\\":53,\\\"totalAssessedAmount\\\":17500,\\\"totalScholarshipCoverage\\\":0,\\\"totalWaived\\\":0,\\\"remainingBalance\\\":17500,\\\"assessmentDate\\\":\\\"2026-09-13 04:17:57\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"assessmentId\\\":53}\"','127.0.0.1','2026-09-12 17:17:57'),(5624,4,'created','charges',209,NULL,'\"{\\\"feeTypeId\\\":1,\\\"amount\\\":11250,\\\"waivedAmount\\\":0,\\\"assessmentId\\\":53,\\\"chargeId\\\":209}\"','127.0.0.1','2026-09-12 17:17:57'),(5625,4,'created','charges',210,NULL,'\"{\\\"feeTypeId\\\":2,\\\"amount\\\":\\\"1500.00\\\",\\\"waivedAmount\\\":0,\\\"assessmentId\\\":53,\\\"chargeId\\\":210}\"','127.0.0.1','2026-09-12 17:17:57'),(5626,4,'created','charges',211,NULL,'\"{\\\"feeTypeId\\\":3,\\\"amount\\\":4500,\\\"waivedAmount\\\":0,\\\"assessmentId\\\":53,\\\"chargeId\\\":211}\"','127.0.0.1','2026-09-12 17:17:57'),(5627,4,'created','charges',212,NULL,'\"{\\\"feeTypeId\\\":4,\\\"amount\\\":\\\"250.00\\\",\\\"waivedAmount\\\":0,\\\"assessmentId\\\":53,\\\"chargeId\\\":212}\"','127.0.0.1','2026-09-12 17:17:57'),(5628,4,'updated','enrollments',53,'\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"evaluated\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13T00:00:00.000000Z\\\",\\\"created_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\"}\"','\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"assessed\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5629,4,'created','enrollmentstatushistory',210,NULL,'\"{\\\"enrollmentId\\\":53,\\\"fromStatus\\\":\\\"evaluated\\\",\\\"toStatus\\\":\\\"assessed\\\",\\\"changedBy\\\":4,\\\"remarks\\\":\\\"Assessment finalized\\\",\\\"changedAt\\\":\\\"2026-09-13 04:17:57\\\",\\\"historyId\\\":210}\"','127.0.0.1','2026-09-12 17:17:57'),(5630,4,'updated','workflowsteps',340,'\"{\\\"workflowStepId\\\":340,\\\"workflowId\\\":53,\\\"officeId\\\":3,\\\"stepOrder\\\":2,\\\"stepStatus\\\":\\\"pending\\\",\\\"signedBy\\\":null,\\\"signedDate\\\":null}\"','\"{\\\"workflowStepId\\\":340,\\\"workflowId\\\":53,\\\"officeId\\\":3,\\\"stepOrder\\\":2,\\\"stepStatus\\\":\\\"completed\\\",\\\"signedBy\\\":4,\\\"signedDate\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5631,4,'updated','enrollmentworkflow',53,'\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":1,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":2,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5632,3,'created','payments',53,NULL,'\"{\\\"enrollmentId\\\":53,\\\"orNumber\\\":\\\"DEMO-OR-0001\\\",\\\"amount\\\":\\\"17500.00\\\",\\\"paymentDate\\\":\\\"2026-09-13 00:00:00\\\",\\\"paymentMode\\\":\\\"cash\\\",\\\"processedBy\\\":3,\\\"paymentStatus\\\":\\\"paid\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"paymentId\\\":53}\"','127.0.0.1','2026-09-12 17:17:57'),(5633,3,'updated','studentassessments',53,'\"{\\\"assessmentId\\\":53,\\\"enrollmentId\\\":53,\\\"totalAssessedAmount\\\":\\\"17500.00\\\",\\\"totalScholarshipCoverage\\\":\\\"0.00\\\",\\\"totalWaived\\\":\\\"0.00\\\",\\\"remainingBalance\\\":\\\"17500.00\\\",\\\"assessmentDate\\\":\\\"2026-09-13T00:00:00.000000Z\\\",\\\"created_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\"}\"','\"{\\\"assessmentId\\\":53,\\\"enrollmentId\\\":53,\\\"totalAssessedAmount\\\":\\\"17500.00\\\",\\\"totalScholarshipCoverage\\\":\\\"0.00\\\",\\\"totalWaived\\\":\\\"0.00\\\",\\\"remainingBalance\\\":0,\\\"assessmentDate\\\":\\\"2026-09-13\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5634,3,'updated','enrollments',53,'\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"assessed\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13T00:00:00.000000Z\\\",\\\"created_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\"}\"','\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"paid\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5635,3,'created','enrollmentstatushistory',211,NULL,'\"{\\\"enrollmentId\\\":53,\\\"fromStatus\\\":\\\"assessed\\\",\\\"toStatus\\\":\\\"paid\\\",\\\"changedBy\\\":3,\\\"remarks\\\":\\\"Full payment received\\\",\\\"changedAt\\\":\\\"2026-09-13 04:17:57\\\",\\\"historyId\\\":211}\"','127.0.0.1','2026-09-12 17:17:57'),(5636,3,'updated','workflowsteps',341,'\"{\\\"workflowStepId\\\":341,\\\"workflowId\\\":53,\\\"officeId\\\":2,\\\"stepOrder\\\":3,\\\"stepStatus\\\":\\\"pending\\\",\\\"signedBy\\\":null,\\\"signedDate\\\":null}\"','\"{\\\"workflowStepId\\\":341,\\\"workflowId\\\":53,\\\"officeId\\\":2,\\\"stepOrder\\\":3,\\\"stepStatus\\\":\\\"completed\\\",\\\"signedBy\\\":3,\\\"signedDate\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5637,3,'updated','enrollmentworkflow',53,'\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":2,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":3,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5638,1,'updated','enrollments',53,'\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"paid\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13T00:00:00.000000Z\\\",\\\"created_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\"}\"','\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"enrolled\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5639,1,'created','enrollmentstatushistory',212,NULL,'\"{\\\"enrollmentId\\\":53,\\\"fromStatus\\\":\\\"paid\\\",\\\"toStatus\\\":\\\"enrolled\\\",\\\"changedBy\\\":1,\\\"remarks\\\":\\\"Registrar approved enrollment\\\",\\\"changedAt\\\":\\\"2026-09-13 04:17:57\\\",\\\"historyId\\\":212}\"','127.0.0.1','2026-09-12 17:17:57'),(5640,1,'updated','enrollments',53,'\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"enrolled\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":null,\\\"enrolledDate\\\":null,\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13T00:00:00.000000Z\\\",\\\"created_at\\\":\\\"2026-09-13T04:17:56.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\"}\"','\"{\\\"enrollmentId\\\":53,\\\"studentId\\\":53,\\\"courseId\\\":3,\\\"majorId\\\":null,\\\"termId\\\":18,\\\"yearLevel\\\":1,\\\"admissionId\\\":27,\\\"studentType\\\":\\\"firstYear\\\",\\\"enrollmentType\\\":\\\"new\\\",\\\"academicStanding\\\":\\\"regular\\\",\\\"enrollmentStatus\\\":\\\"enrolled\\\",\\\"evaluatedBy\\\":5,\\\"registrarProcessedBy\\\":1,\\\"enrolledDate\\\":\\\"2026-09-13 04:17:57\\\",\\\"formIssuedDate\\\":null,\\\"formSignedDate\\\":\\\"2026-09-13\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:56\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5641,1,'updated','workflowsteps',342,'\"{\\\"workflowStepId\\\":342,\\\"workflowId\\\":53,\\\"officeId\\\":1,\\\"stepOrder\\\":4,\\\"stepStatus\\\":\\\"pending\\\",\\\"signedBy\\\":null,\\\"signedDate\\\":null}\"','\"{\\\"workflowStepId\\\":342,\\\"workflowId\\\":53,\\\"officeId\\\":1,\\\"stepOrder\\\":4,\\\"stepStatus\\\":\\\"completed\\\",\\\"signedBy\\\":1,\\\"signedDate\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5642,1,'updated','enrollmentworkflow',53,'\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":3,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":4,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5643,6,'updated','workflowsteps',343,'\"{\\\"workflowStepId\\\":343,\\\"workflowId\\\":53,\\\"officeId\\\":5,\\\"stepOrder\\\":5,\\\"stepStatus\\\":\\\"pending\\\",\\\"signedBy\\\":null,\\\"signedDate\\\":null}\"','\"{\\\"workflowStepId\\\":343,\\\"workflowId\\\":53,\\\"officeId\\\":5,\\\"stepOrder\\\":5,\\\"stepStatus\\\":\\\"completed\\\",\\\"signedBy\\\":6,\\\"signedDate\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5644,6,'updated','enrollmentworkflow',53,'\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":4,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":5,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5645,10,'created','clinicrecords',53,NULL,'\"{\\\"enrollmentId\\\":53,\\\"heightCm\\\":170,\\\"weightKg\\\":62,\\\"bloodPressure\\\":\\\"120\\\\/80\\\",\\\"philhealthNumber\\\":\\\"PH-DEMO-0001\\\",\\\"philhealthRegistered\\\":true,\\\"assessmentNotes\\\":\\\"Fit for enrollment\\\",\\\"findings\\\":\\\"Normal\\\",\\\"assessmentDate\\\":\\\"2026-09-13 00:00:00\\\",\\\"clinicStaffId\\\":10,\\\"status\\\":\\\"completed\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"clinicRecordId\\\":53}\"','127.0.0.1','2026-09-12 17:17:57'),(5646,10,'updated','workflowsteps',344,'\"{\\\"workflowStepId\\\":344,\\\"workflowId\\\":53,\\\"officeId\\\":11,\\\"stepOrder\\\":6,\\\"stepStatus\\\":\\\"pending\\\",\\\"signedBy\\\":null,\\\"signedDate\\\":null}\"','\"{\\\"workflowStepId\\\":344,\\\"workflowId\\\":53,\\\"officeId\\\":11,\\\"stepOrder\\\":6,\\\"stepStatus\\\":\\\"completed\\\",\\\"signedBy\\\":10,\\\"signedDate\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5647,10,'updated','enrollmentworkflow',53,'\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":5,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":6,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5648,11,'created','idrequests',53,NULL,'\"{\\\"enrollmentId\\\":53,\\\"requestReason\\\":\\\"newStudent\\\",\\\"emergencyContactName\\\":\\\"Maria Dela Cruz\\\",\\\"emergencyContactNumber\\\":\\\"09171234568\\\",\\\"bloodType\\\":\\\"O+\\\",\\\"cardPhotoPath\\\":null,\\\"producedByVendor\\\":null,\\\"requestDate\\\":\\\"2026-09-13 04:17:57\\\",\\\"status\\\":\\\"pending\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"idRequestId\\\":53}\"','127.0.0.1','2026-09-12 17:17:57'),(5649,11,'created','studentids',53,NULL,'\"{\\\"studentId\\\":53,\\\"idRequestId\\\":53,\\\"qrCode\\\":\\\"SEAIT-DEMO-53\\\",\\\"issueDate\\\":\\\"2026-09-13 04:17:57\\\",\\\"validationStatus\\\":\\\"pendingValidation\\\",\\\"securityPhotoPath\\\":null,\\\"idId\\\":53}\"','127.0.0.1','2026-09-12 17:17:57'),(5650,11,'updated','idrequests',53,'\"{\\\"idRequestId\\\":53,\\\"enrollmentId\\\":53,\\\"requestReason\\\":\\\"newStudent\\\",\\\"emergencyContactName\\\":\\\"Maria Dela Cruz\\\",\\\"emergencyContactNumber\\\":\\\"09171234568\\\",\\\"bloodType\\\":\\\"O+\\\",\\\"cardPhotoPath\\\":null,\\\"producedByVendor\\\":null,\\\"requestDate\\\":\\\"2026-09-13T00:00:00.000000Z\\\",\\\"status\\\":\\\"pending\\\",\\\"reissueReason\\\":null,\\\"is_reissue\\\":false,\\\"created_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\"}\"','\"{\\\"idRequestId\\\":53,\\\"enrollmentId\\\":53,\\\"requestReason\\\":\\\"newStudent\\\",\\\"emergencyContactName\\\":\\\"Maria Dela Cruz\\\",\\\"emergencyContactNumber\\\":\\\"09171234568\\\",\\\"bloodType\\\":\\\"O+\\\",\\\"cardPhotoPath\\\":null,\\\"producedByVendor\\\":null,\\\"requestDate\\\":\\\"2026-09-13\\\",\\\"status\\\":\\\"cardProduced\\\",\\\"reissueReason\\\":null,\\\"is_reissue\\\":0,\\\"created_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:57\\\"}\"','127.0.0.1','2026-09-12 17:17:57'),(5651,11,'updated','studentids',53,'\"{\\\"idId\\\":53,\\\"studentId\\\":53,\\\"idRequestId\\\":53,\\\"qrCode\\\":\\\"SEAIT-DEMO-53\\\",\\\"issueDate\\\":\\\"2026-09-13T00:00:00.000000Z\\\",\\\"validationStatus\\\":\\\"pendingValidation\\\",\\\"securityPhotoPath\\\":null,\\\"validatedBy\\\":null,\\\"validatedDate\\\":null}\"','\"{\\\"idId\\\":53,\\\"studentId\\\":53,\\\"idRequestId\\\":53,\\\"qrCode\\\":\\\"SEAIT-DEMO-53\\\",\\\"issueDate\\\":\\\"2026-09-13\\\",\\\"validationStatus\\\":\\\"active\\\",\\\"securityPhotoPath\\\":null,\\\"validatedBy\\\":11,\\\"validatedDate\\\":\\\"2026-09-13 04:17:58\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5652,11,'updated','idrequests',53,'\"{\\\"idRequestId\\\":53,\\\"enrollmentId\\\":53,\\\"requestReason\\\":\\\"newStudent\\\",\\\"emergencyContactName\\\":\\\"Maria Dela Cruz\\\",\\\"emergencyContactNumber\\\":\\\"09171234568\\\",\\\"bloodType\\\":\\\"O+\\\",\\\"cardPhotoPath\\\":null,\\\"producedByVendor\\\":null,\\\"requestDate\\\":\\\"2026-09-13T00:00:00.000000Z\\\",\\\"status\\\":\\\"cardProduced\\\",\\\"reissueReason\\\":null,\\\"is_reissue\\\":false,\\\"created_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:57.000000Z\\\"}\"','\"{\\\"idRequestId\\\":53,\\\"enrollmentId\\\":53,\\\"requestReason\\\":\\\"newStudent\\\",\\\"emergencyContactName\\\":\\\"Maria Dela Cruz\\\",\\\"emergencyContactNumber\\\":\\\"09171234568\\\",\\\"bloodType\\\":\\\"O+\\\",\\\"cardPhotoPath\\\":null,\\\"producedByVendor\\\":null,\\\"requestDate\\\":\\\"2026-09-13\\\",\\\"status\\\":\\\"validated\\\",\\\"reissueReason\\\":null,\\\"is_reissue\\\":0,\\\"created_at\\\":\\\"2026-09-13 04:17:57\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:58\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5653,11,'updated','workflowsteps',345,'\"{\\\"workflowStepId\\\":345,\\\"workflowId\\\":53,\\\"officeId\\\":22,\\\"stepOrder\\\":7,\\\"stepStatus\\\":\\\"pending\\\",\\\"signedBy\\\":null,\\\"signedDate\\\":null}\"','\"{\\\"workflowStepId\\\":345,\\\"workflowId\\\":53,\\\"officeId\\\":22,\\\"stepOrder\\\":7,\\\"stepStatus\\\":\\\"completed\\\",\\\"signedBy\\\":11,\\\"signedDate\\\":\\\"2026-09-13 04:17:58\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5654,11,'updated','enrollmentworkflow',53,'\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":6,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":7,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5655,11,'updated','enrollmentworkflow',53,'\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":7,\\\"workflowStatus\\\":\\\"inProgress\\\"}\"','\"{\\\"workflowId\\\":53,\\\"enrollmentId\\\":53,\\\"currentStep\\\":7,\\\"workflowStatus\\\":\\\"completed\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5656,NULL,'created','students',54,NULL,'\"{\\\"schoolIdNumber\\\":\\\"DEMO-2026-002\\\",\\\"lastName\\\":\\\"Reyes\\\",\\\"firstName\\\":\\\"Maria\\\",\\\"middleName\\\":\\\"S\\\",\\\"suffix\\\":\\\"N\\\\/A\\\",\\\"gender\\\":\\\"female\\\",\\\"birthdate\\\":\\\"2003-05-15 00:00:00\\\",\\\"birthplace\\\":\\\"Test City\\\",\\\"citizenship\\\":\\\"Filipino\\\",\\\"civilStatus\\\":\\\"single\\\",\\\"religionId\\\":1,\\\"contactNumber\\\":\\\"09171234570\\\",\\\"telephoneNumber\\\":null,\\\"semestersCompleted\\\":4,\\\"yearsInInstitution\\\":2,\\\"email\\\":\\\"demo.maria.reyes@example.com\\\",\\\"username\\\":\\\"demo_maria_r\\\",\\\"passwordHash\\\":\\\"[REDACTED]\\\",\\\"status\\\":\\\"active\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:58\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:58\\\",\\\"studentId\\\":54}\"','127.0.0.1','2026-09-12 17:17:58'),(5657,1,'created','studentclearances',27,NULL,'\"{\\\"studentId\\\":54,\\\"clearancePeriodId\\\":1,\\\"overallStatus\\\":\\\"pending\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:58\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:58\\\",\\\"studentClearanceId\\\":27}\"','127.0.0.1','2026-09-12 17:17:58'),(5658,1,'created','clearanceapprovals',261,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":1,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":261}\"','127.0.0.1','2026-09-12 17:17:58'),(5659,1,'created','clearanceapprovals',262,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":2,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":262}\"','127.0.0.1','2026-09-12 17:17:58'),(5660,1,'created','clearanceapprovals',263,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":3,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":263}\"','127.0.0.1','2026-09-12 17:17:58'),(5661,1,'created','clearanceapprovals',264,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":4,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":264}\"','127.0.0.1','2026-09-12 17:17:58'),(5662,1,'created','clearanceapprovals',265,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":5,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":265}\"','127.0.0.1','2026-09-12 17:17:58'),(5663,1,'created','clearanceapprovals',266,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":6,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":266}\"','127.0.0.1','2026-09-12 17:17:58'),(5664,1,'created','clearanceapprovals',267,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":7,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":267}\"','127.0.0.1','2026-09-12 17:17:58'),(5665,1,'created','clearanceapprovals',268,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":8,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":268}\"','127.0.0.1','2026-09-12 17:17:58'),(5666,1,'created','clearanceapprovals',269,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":9,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":269}\"','127.0.0.1','2026-09-12 17:17:58'),(5667,1,'created','clearanceapprovals',270,NULL,'\"{\\\"remarks\\\":\\\"\\\",\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":10,\\\"status\\\":\\\"pending\\\",\\\"clearanceApprovalId\\\":270}\"','127.0.0.1','2026-09-12 17:17:58'),(5668,1,'updated','clearanceapprovals',261,'\"{\\\"clearanceApprovalId\\\":261,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":1,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":261,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":1,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":1,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5669,3,'updated','clearanceapprovals',262,'\"{\\\"clearanceApprovalId\\\":262,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":2,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":262,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":2,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":3,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5670,4,'updated','clearanceapprovals',263,'\"{\\\"clearanceApprovalId\\\":263,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":3,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":263,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":3,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":4,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5671,5,'updated','clearanceapprovals',264,'\"{\\\"clearanceApprovalId\\\":264,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":4,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":264,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":4,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":5,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5672,6,'updated','clearanceapprovals',265,'\"{\\\"clearanceApprovalId\\\":265,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":5,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":265,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":5,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":6,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5673,7,'updated','clearanceapprovals',266,'\"{\\\"clearanceApprovalId\\\":266,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":6,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":266,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":6,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":7,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5674,8,'updated','clearanceapprovals',267,'\"{\\\"clearanceApprovalId\\\":267,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":7,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":267,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":7,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":8,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5675,9,'updated','clearanceapprovals',268,'\"{\\\"clearanceApprovalId\\\":268,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":8,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":268,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":8,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":9,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5676,10,'updated','clearanceapprovals',269,'\"{\\\"clearanceApprovalId\\\":269,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":9,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":269,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":9,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":10,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5677,11,'updated','clearanceapprovals',270,'\"{\\\"clearanceApprovalId\\\":270,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":10,\\\"status\\\":\\\"pending\\\",\\\"approvedBy\\\":null,\\\"approvalDate\\\":null,\\\"remarks\\\":\\\"\\\"}\"','\"{\\\"clearanceApprovalId\\\":270,\\\"studentClearanceId\\\":27,\\\"clearanceRequirementId\\\":10,\\\"status\\\":\\\"approved\\\",\\\"approvedBy\\\":11,\\\"approvalDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"remarks\\\":\\\"\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5678,11,'updated','studentclearances',27,'\"{\\\"studentClearanceId\\\":27,\\\"studentId\\\":54,\\\"clearancePeriodId\\\":1,\\\"overallStatus\\\":\\\"pending\\\",\\\"extendedDeadline\\\":null,\\\"receivedBy\\\":null,\\\"receivedDate\\\":null,\\\"created_at\\\":\\\"2026-09-13T04:17:58.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:58.000000Z\\\"}\"','\"{\\\"studentClearanceId\\\":27,\\\"studentId\\\":54,\\\"clearancePeriodId\\\":1,\\\"overallStatus\\\":\\\"approved\\\",\\\"extendedDeadline\\\":null,\\\"receivedBy\\\":null,\\\"receivedDate\\\":null,\\\"created_at\\\":\\\"2026-09-13 04:17:58\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:58\\\"}\"','127.0.0.1','2026-09-12 17:17:58'),(5679,1,'updated','studentclearances',27,'\"{\\\"studentClearanceId\\\":27,\\\"studentId\\\":54,\\\"clearancePeriodId\\\":1,\\\"overallStatus\\\":\\\"approved\\\",\\\"extendedDeadline\\\":null,\\\"receivedBy\\\":null,\\\"receivedDate\\\":null,\\\"created_at\\\":\\\"2026-09-13T04:17:58.000000Z\\\",\\\"updated_at\\\":\\\"2026-09-13T04:17:58.000000Z\\\"}\"','\"{\\\"studentClearanceId\\\":27,\\\"studentId\\\":54,\\\"clearancePeriodId\\\":1,\\\"overallStatus\\\":\\\"approved\\\",\\\"extendedDeadline\\\":null,\\\"receivedBy\\\":1,\\\"receivedDate\\\":\\\"2026-09-13 04:17:58\\\",\\\"created_at\\\":\\\"2026-09-13 04:17:58\\\",\\\"updated_at\\\":\\\"2026-09-13 04:17:58\\\"}\"','127.0.0.1','2026-09-12 17:17:59'),(5680,8,'created','examresults',29,NULL,'\"{\\\"studentId\\\":54,\\\"courseId\\\":5,\\\"termId\\\":18,\\\"examStage\\\":\\\"retention\\\",\\\"examType\\\":\\\"courseSpecific\\\",\\\"examResult\\\":\\\"pass\\\",\\\"examDate\\\":\\\"2026-09-13 00:00:00\\\",\\\"examId\\\":29}\"','127.0.0.1','2026-09-12 17:55:39'),(5681,NULL,'created','students',55,NULL,'\"{\\\"schoolIdNumber\\\":\\\"DEMO-2026-003\\\",\\\"lastName\\\":\\\"Santos\\\",\\\"firstName\\\":\\\"Pedro\\\",\\\"middleName\\\":\\\"R\\\",\\\"suffix\\\":\\\"N\\\\/A\\\",\\\"gender\\\":\\\"male\\\",\\\"birthdate\\\":\\\"2004-03-20 00:00:00\\\",\\\"birthplace\\\":\\\"Test City\\\",\\\"citizenship\\\":\\\"Filipino\\\",\\\"civilStatus\\\":\\\"single\\\",\\\"religionId\\\":1,\\\"contactNumber\\\":\\\"09171234580\\\",\\\"telephoneNumber\\\":null,\\\"semestersCompleted\\\":0,\\\"yearsInInstitution\\\":0,\\\"email\\\":\\\"demo.pedro@example.com\\\",\\\"username\\\":\\\"demo_pedro\\\",\\\"passwordHash\\\":\\\"[REDACTED]\\\",\\\"status\\\":\\\"active\\\",\\\"updated_at\\\":\\\"2026-09-13 05:03:28\\\",\\\"created_at\\\":\\\"2026-09-13 05:03:28\\\",\\\"studentId\\\":55}\"','127.0.0.1','2026-09-12 18:03:28'),(5682,NULL,'created','admissions',28,NULL,'\"{\\\"studentId\\\":55,\\\"courseId\\\":3,\\\"termId\\\":18,\\\"applicantType\\\":\\\"firstYear\\\",\\\"applicationMode\\\":\\\"faceToFace\\\",\\\"admissionStatus\\\":\\\"pending\\\",\\\"updated_at\\\":\\\"2026-09-13 05:03:29\\\",\\\"created_at\\\":\\\"2026-09-13 05:03:29\\\",\\\"admissionId\\\":28}\"','127.0.0.1','2026-09-12 18:03:29'),(5683,NULL,'created','students',56,NULL,'\"{\\\"schoolIdNumber\\\":\\\"DEMO-2026-004\\\",\\\"lastName\\\":\\\"Bautista\\\",\\\"firstName\\\":\\\"Liza\\\",\\\"middleName\\\":\\\"M\\\",\\\"suffix\\\":\\\"N\\\\/A\\\",\\\"gender\\\":\\\"female\\\",\\\"birthdate\\\":\\\"2003-11-02 00:00:00\\\",\\\"birthplace\\\":\\\"Test City\\\",\\\"citizenship\\\":\\\"Filipino\\\",\\\"civilStatus\\\":\\\"single\\\",\\\"religionId\\\":1,\\\"contactNumber\\\":\\\"09171234581\\\",\\\"telephoneNumber\\\":null,\\\"semestersCompleted\\\":4,\\\"yearsInInstitution\\\":2,\\\"email\\\":\\\"demo.liza@example.com\\\",\\\"username\\\":\\\"demo_liza\\\",\\\"passwordHash\\\":\\\"[REDACTED]\\\",\\\"status\\\":\\\"active\\\",\\\"updated_at\\\":\\\"2026-09-13 05:03:29\\\",\\\"created_at\\\":\\\"2026-09-13 05:03:29\\\",\\\"studentId\\\":56}\"','127.0.0.1','2026-09-12 18:03:29'),(7388,1,'created','documentprintlog',1,NULL,'\"{\\\"enrollmentId\\\":null,\\\"documentType\\\":\\\"clearanceSlip\\\",\\\"printedDate\\\":\\\"2026-09-13 05:33:25\\\",\\\"printedBy\\\":1,\\\"documentNumber\\\":1,\\\"printLogId\\\":1}\"','::1','2026-09-12 18:33:25'),(7389,1,'created','documentprintlog',2,NULL,'\"{\\\"enrollmentId\\\":55,\\\"documentType\\\":\\\"certificate\\\",\\\"printedDate\\\":\\\"2026-09-13 05:33:51\\\",\\\"printedBy\\\":1,\\\"documentNumber\\\":1,\\\"printLogId\\\":2}\"','::1','2026-09-12 18:33:51'),(7390,1,'created','documentprintlog',3,NULL,'\"{\\\"enrollmentId\\\":55,\\\"documentType\\\":\\\"subjectLoad\\\",\\\"printedDate\\\":\\\"2026-09-13 05:33:53\\\",\\\"printedBy\\\":1,\\\"documentNumber\\\":1,\\\"printLogId\\\":3}\"','::1','2026-09-12 18:33:53'),(7391,1,'created','documentprintlog',4,NULL,'\"{\\\"enrollmentId\\\":null,\\\"documentType\\\":\\\"clearanceSlip\\\",\\\"printedDate\\\":\\\"2026-09-13 05:59:31\\\",\\\"printedBy\\\":1,\\\"documentNumber\\\":2,\\\"printLogId\\\":4}\"','::1','2026-09-12 18:59:31'),(7392,1,'created','documentprintlog',5,NULL,'\"{\\\"enrollmentId\\\":55,\\\"documentType\\\":\\\"certificate\\\",\\\"printedDate\\\":\\\"2026-09-13 05:59:35\\\",\\\"printedBy\\\":1,\\\"documentNumber\\\":2,\\\"printLogId\\\":5}\"','::1','2026-09-12 18:59:35'),(7393,1,'created','documentprintlog',6,NULL,'\"{\\\"enrollmentId\\\":55,\\\"documentType\\\":\\\"subjectLoad\\\",\\\"printedDate\\\":\\\"2026-09-13 05:59:37\\\",\\\"printedBy\\\":1,\\\"documentNumber\\\":2,\\\"printLogId\\\":6}\"','::1','2026-09-12 18:59:37');
/*!40000 ALTER TABLE `auditlogs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blocks`
--

DROP TABLE IF EXISTS `blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blocks` (
  `blockId` int NOT NULL AUTO_INCREMENT,
  `courseId` int NOT NULL,
  `termId` int NOT NULL,
  `yearLevel` int NOT NULL,
  `blockName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `maxStudents` int NOT NULL,
  PRIMARY KEY (`blockId`),
  KEY `fk_sections_courseid` (`courseId`),
  KEY `fk_sections_termid` (`termId`),
  KEY `idx_blocks_filter_order` (`courseId`,`termId`,`yearLevel`,`blockId`),
  CONSTRAINT `blocks_courseid_foreign` FOREIGN KEY (`courseId`) REFERENCES `courses` (`courseId`) ON UPDATE CASCADE,
  CONSTRAINT `blocks_termid_foreign` FOREIGN KEY (`termId`) REFERENCES `academicterms` (`termId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blocks`
--

LOCK TABLES `blocks` WRITE;
/*!40000 ALTER TABLE `blocks` DISABLE KEYS */;
INSERT INTO `blocks` VALUES (53,3,18,1,'BSCrim 1-A',40);
/*!40000 ALTER TABLE `blocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `charges`
--

DROP TABLE IF EXISTS `charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `charges` (
  `chargeId` int NOT NULL AUTO_INCREMENT,
  `assessmentId` int NOT NULL,
  `feeTypeId` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `waivedAmount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`chargeId`),
  KEY `fk_charges_assessmentid` (`assessmentId`),
  KEY `fk_charges_feetypeid` (`feeTypeId`),
  CONSTRAINT `charges_assessmentid_foreign` FOREIGN KEY (`assessmentId`) REFERENCES `studentassessments` (`assessmentId`) ON UPDATE CASCADE,
  CONSTRAINT `charges_feetypeid_foreign` FOREIGN KEY (`feeTypeId`) REFERENCES `feetypes` (`feeTypeId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=309 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `charges`
--

LOCK TABLES `charges` WRITE;
/*!40000 ALTER TABLE `charges` DISABLE KEYS */;
INSERT INTO `charges` VALUES (209,53,1,11250.00,0.00),(210,53,2,1500.00,0.00),(211,53,3,4500.00,0.00),(212,53,4,250.00,0.00);
/*!40000 ALTER TABLE `charges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearanceapprovals`
--

DROP TABLE IF EXISTS `clearanceapprovals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clearanceapprovals` (
  `clearanceApprovalId` int NOT NULL AUTO_INCREMENT,
  `studentClearanceId` int NOT NULL,
  `clearanceRequirementId` int NOT NULL,
  `status` enum('pending','approved','rejected','waived') COLLATE utf8mb4_unicode_ci NOT NULL,
  `approvedBy` int DEFAULT NULL,
  `approvalDate` date DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`clearanceApprovalId`),
  KEY `fk_clearanceapprovals_studentclearanceid` (`studentClearanceId`),
  KEY `fk_clearanceapprovals_clearancerequirementid` (`clearanceRequirementId`),
  KEY `fk_clearanceapprovals_approvedby` (`approvedBy`),
  KEY `idx_clearanceapprovals_clearance_status` (`studentClearanceId`,`status`),
  CONSTRAINT `clearanceapprovals_approvedby_foreign` FOREIGN KEY (`approvedBy`) REFERENCES `staffusers` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `clearanceapprovals_clearancerequirementid_foreign` FOREIGN KEY (`clearanceRequirementId`) REFERENCES `clearancerequirements` (`clearanceRequirementId`) ON UPDATE CASCADE,
  CONSTRAINT `clearanceapprovals_studentclearanceid_foreign` FOREIGN KEY (`studentClearanceId`) REFERENCES `studentclearances` (`studentClearanceId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=391 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearanceapprovals`
--

LOCK TABLES `clearanceapprovals` WRITE;
/*!40000 ALTER TABLE `clearanceapprovals` DISABLE KEYS */;
INSERT INTO `clearanceapprovals` VALUES (261,27,1,'approved',1,'2026-09-13',''),(262,27,2,'approved',3,'2026-09-13',''),(263,27,3,'approved',4,'2026-09-13',''),(264,27,4,'approved',5,'2026-09-13',''),(265,27,5,'approved',6,'2026-09-13',''),(266,27,6,'approved',7,'2026-09-13',''),(267,27,7,'approved',8,'2026-09-13',''),(268,27,8,'approved',9,'2026-09-13',''),(269,27,9,'approved',10,'2026-09-13',''),(270,27,10,'approved',11,'2026-09-13','');
/*!40000 ALTER TABLE `clearanceapprovals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearanceperiods`
--

DROP TABLE IF EXISTS `clearanceperiods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clearanceperiods` (
  `clearancePeriodId` int NOT NULL AUTO_INCREMENT,
  `termId` int NOT NULL,
  `clearanceStartDate` date NOT NULL,
  `clearanceEndDate` date NOT NULL,
  `periodStatus` enum('open','closed','extended') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`clearancePeriodId`),
  KEY `fk_clearanceperiods_termid` (`termId`),
  CONSTRAINT `clearanceperiods_termid_foreign` FOREIGN KEY (`termId`) REFERENCES `academicterms` (`termId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearanceperiods`
--

LOCK TABLES `clearanceperiods` WRITE;
/*!40000 ALTER TABLE `clearanceperiods` DISABLE KEYS */;
INSERT INTO `clearanceperiods` VALUES (1,18,'2026-04-01','2026-05-31','open');
/*!40000 ALTER TABLE `clearanceperiods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearancerequirements`
--

DROP TABLE IF EXISTS `clearancerequirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clearancerequirements` (
  `clearanceRequirementId` int NOT NULL AUTO_INCREMENT,
  `officeId` int NOT NULL,
  PRIMARY KEY (`clearanceRequirementId`),
  KEY `fk_clearancerequirements_officeid` (`officeId`),
  CONSTRAINT `clearancerequirements_officeid_foreign` FOREIGN KEY (`officeId`) REFERENCES `offices` (`officeId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearancerequirements`
--

LOCK TABLES `clearancerequirements` WRITE;
/*!40000 ALTER TABLE `clearancerequirements` DISABLE KEYS */;
INSERT INTO `clearancerequirements` VALUES (1,1),(2,2),(3,3),(4,4),(5,5),(6,6),(7,7),(8,8),(9,11),(10,22);
/*!40000 ALTER TABLE `clearancerequirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinicrecords`
--

DROP TABLE IF EXISTS `clinicrecords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinicrecords` (
  `clinicRecordId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int NOT NULL,
  `heightCm` decimal(5,1) NOT NULL DEFAULT '0.0',
  `weightKg` decimal(5,1) NOT NULL DEFAULT '0.0',
  `bloodPressure` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `philhealthNumber` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `philhealthRegistered` tinyint(1) NOT NULL DEFAULT '0',
  `assessmentNotes` text COLLATE utf8mb4_unicode_ci,
  `findings` text COLLATE utf8mb4_unicode_ci,
  `clinicStaffId` int DEFAULT NULL,
  `assessmentDate` date DEFAULT NULL,
  `status` enum('pending','completed','reopened') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`clinicRecordId`),
  KEY `fk_clinicrecords_enrollmentid` (`enrollmentId`),
  KEY `fk_clinicrecords_clinicstaffid` (`clinicStaffId`),
  CONSTRAINT `clinicrecords_clinicstaffid_foreign` FOREIGN KEY (`clinicStaffId`) REFERENCES `staffusers` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `clinicrecords_enrollmentid_foreign` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinicrecords`
--

LOCK TABLES `clinicrecords` WRITE;
/*!40000 ALTER TABLE `clinicrecords` DISABLE KEYS */;
INSERT INTO `clinicrecords` VALUES (53,53,170.0,62.0,'120/80','PH-DEMO-0001',1,'Fit for enrollment','Normal',10,'2026-09-13','completed','2026-09-13 15:17:57','2026-09-13 15:17:57');
/*!40000 ALTER TABLE `clinicrecords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `courseId` int NOT NULL AUTO_INCREMENT,
  `unitId` int NOT NULL,
  `courseName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `courseCode` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requiresEntranceExam` tinyint(1) NOT NULL,
  `requiresRetentionExam` tinyint(1) NOT NULL,
  PRIMARY KEY (`courseId`),
  KEY `fk_courses_unitid` (`unitId`),
  CONSTRAINT `courses_unitid_foreign` FOREIGN KEY (`unitId`) REFERENCES `academicunits` (`unitId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,4,'Bachelor of Science in Information Technology','BSIT',0,0),(2,4,'Bachelor of Science in Computer Science','BSCS',0,0),(3,2,'Bachelor of Science in Criminology','BSCrim',1,0),(4,1,'Bachelor of Science in Agriculture','BSA',0,0),(5,3,'Bachelor of Science in Business Administration','BSBA',0,1),(6,6,'Bachelor of Secondary Education','BSEd',0,0);
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `creditedsubjects`
--

DROP TABLE IF EXISTS `creditedsubjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `creditedsubjects` (
  `creditedId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int NOT NULL,
  `transferRecordId` int DEFAULT NULL,
  `previousSubjectName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creditedToSubjectId` int DEFAULT NULL,
  `creditedUnits` decimal(3,1) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`creditedId`),
  KEY `fk_creditedsubjects_enrollmentid` (`enrollmentId`),
  KEY `fk_creditedsubjects_transferrecordid` (`transferRecordId`),
  KEY `fk_creditedsubjects_creditedtosubjectid` (`creditedToSubjectId`),
  CONSTRAINT `creditedsubjects_enrollmentid_foreign` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`) ON UPDATE CASCADE,
  CONSTRAINT `fk_creditedsubjects_creditedtosubjectid` FOREIGN KEY (`creditedToSubjectId`) REFERENCES `subjects` (`subjectId`),
  CONSTRAINT `fk_creditedsubjects_transferrecordid` FOREIGN KEY (`transferRecordId`) REFERENCES `transferacademicrecords` (`transferRecordId`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `creditedsubjects`
--

LOCK TABLES `creditedsubjects` WRITE;
/*!40000 ALTER TABLE `creditedsubjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `creditedsubjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculums`
--

DROP TABLE IF EXISTS `curriculums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculums` (
  `curriculumId` int NOT NULL AUTO_INCREMENT,
  `courseId` int NOT NULL,
  `majorId` int DEFAULT NULL,
  `effectiveYear` date NOT NULL,
  `curriculumName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`curriculumId`),
  KEY `fk_curriculums_courseid` (`courseId`),
  KEY `fk_curriculums_majorid` (`majorId`),
  CONSTRAINT `curriculums_courseid_foreign` FOREIGN KEY (`courseId`) REFERENCES `courses` (`courseId`) ON UPDATE CASCADE,
  CONSTRAINT `curriculums_majorid_foreign` FOREIGN KEY (`majorId`) REFERENCES `majors` (`majorId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculums`
--

LOCK TABLES `curriculums` WRITE;
/*!40000 ALTER TABLE `curriculums` DISABLE KEYS */;
INSERT INTO `curriculums` VALUES (1,3,NULL,'2026-06-01','BSCrim 2026 Curriculum');
/*!40000 ALTER TABLE `curriculums` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculumsubjects`
--

DROP TABLE IF EXISTS `curriculumsubjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculumsubjects` (
  `curriculumSubjectId` int NOT NULL AUTO_INCREMENT,
  `curriculumId` int NOT NULL,
  `subjectId` int NOT NULL,
  `prerequisiteSubjectId` int DEFAULT NULL,
  `yearLevel` int NOT NULL,
  `semesterOffered` enum('1st','2nd','Summer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_elective` tinyint(1) NOT NULL DEFAULT '0',
  `elective_group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `elective_min_choices` tinyint unsigned DEFAULT NULL,
  `elective_max_choices` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`curriculumSubjectId`),
  KEY `fk_curriculumsubjects_curriculumid` (`curriculumId`),
  KEY `fk_curriculumsubjects_subjectid` (`subjectId`),
  KEY `fk_curriculumsubjects_prerequisitesubjectid` (`prerequisiteSubjectId`),
  CONSTRAINT `curriculumsubjects_curriculumid_foreign` FOREIGN KEY (`curriculumId`) REFERENCES `curriculums` (`curriculumId`) ON UPDATE CASCADE,
  CONSTRAINT `fk_curriculumsubjects_prerequisitesubjectid` FOREIGN KEY (`prerequisiteSubjectId`) REFERENCES `subjects` (`subjectId`),
  CONSTRAINT `fk_curriculumsubjects_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculumsubjects`
--

LOCK TABLES `curriculumsubjects` WRITE;
/*!40000 ALTER TABLE `curriculumsubjects` DISABLE KEYS */;
INSERT INTO `curriculumsubjects` VALUES (1,1,1,NULL,1,'1st',0,NULL,NULL,NULL),(2,1,2,NULL,1,'1st',0,NULL,NULL,NULL),(3,1,3,NULL,1,'1st',0,NULL,NULL,NULL),(4,1,4,NULL,1,'1st',0,NULL,NULL,NULL),(5,1,5,NULL,1,'2nd',0,NULL,NULL,NULL),(6,1,6,NULL,1,'2nd',0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `curriculumsubjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentprintlog`
--

DROP TABLE IF EXISTS `documentprintlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentprintlog` (
  `printLogId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int DEFAULT NULL,
  `documentType` enum('subjectLoad','classCard','certificate','clearanceSlip','blockSchedule','enrollmentForm') COLLATE utf8mb4_unicode_ci NOT NULL,
  `printedDate` datetime NOT NULL,
  `printedBy` int NOT NULL,
  `documentNumber` int NOT NULL,
  PRIMARY KEY (`printLogId`),
  KEY `fk_documentprintlog_enrollmentid` (`enrollmentId`),
  KEY `fk_documentprintlog_printedby` (`printedBy`),
  CONSTRAINT `documentprintlog_enrollmentid_foreign` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`) ON UPDATE CASCADE,
  CONSTRAINT `documentprintlog_printedby_foreign` FOREIGN KEY (`printedBy`) REFERENCES `staffusers` (`userId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentprintlog`
--

LOCK TABLES `documentprintlog` WRITE;
/*!40000 ALTER TABLE `documentprintlog` DISABLE KEYS */;
INSERT INTO `documentprintlog` VALUES (1,NULL,'clearanceSlip','2026-09-13 05:33:25',1,1),(2,55,'certificate','2026-09-13 05:33:51',1,1),(3,55,'subjectLoad','2026-09-13 05:33:53',1,1),(4,NULL,'clearanceSlip','2026-09-13 05:59:31',1,2),(5,55,'certificate','2026-09-13 05:59:35',1,2),(6,55,'subjectLoad','2026-09-13 05:59:37',1,2);
/*!40000 ALTER TABLE `documentprintlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `documentId` int NOT NULL AUTO_INCREMENT,
  `submissionId` int NOT NULL,
  `fileUrl` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fileType` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploadedDate` date NOT NULL,
  `verifiedBy` int DEFAULT NULL,
  PRIMARY KEY (`documentId`),
  KEY `fk_documents_submissionid` (`submissionId`),
  KEY `fk_documents_verifiedby` (`verifiedBy`),
  CONSTRAINT `documents_submissionid_foreign` FOREIGN KEY (`submissionId`) REFERENCES `studentrequirementsubmissions` (`submissionId`) ON UPDATE CASCADE,
  CONSTRAINT `documents_verifiedby_foreign` FOREIGN KEY (`verifiedBy`) REFERENCES `staffusers` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `educationalinstitutions`
--

DROP TABLE IF EXISTS `educationalinstitutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `educationalinstitutions` (
  `institutionId` int NOT NULL AUTO_INCREMENT,
  `institutionName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `institutionType` enum('elementary','juniorHigh','seniorHigh','vocational','college') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cityMunicipality` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`institutionId`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `educationalinstitutions`
--

LOCK TABLES `educationalinstitutions` WRITE;
/*!40000 ALTER TABLE `educationalinstitutions` DISABLE KEYS */;
INSERT INTO `educationalinstitutions` VALUES (53,'Test National High School','seniorHigh','Davao City','Davao del Sur');
/*!40000 ALTER TABLE `educationalinstitutions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrolledsubjects`
--

DROP TABLE IF EXISTS `enrolledsubjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrolledsubjects` (
  `enrolledSubjectId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int NOT NULL,
  `subjectId` int NOT NULL,
  `blockId` int DEFAULT NULL,
  `scheduleId` int DEFAULT NULL,
  `grade` decimal(3,2) DEFAULT NULL,
  `status` enum('proposed','confirmed','dropped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_number` tinyint unsigned NOT NULL DEFAULT '1',
  `original_enrolled_subject_id` int DEFAULT NULL,
  PRIMARY KEY (`enrolledSubjectId`),
  UNIQUE KEY `uq_enrollment_subject_attempt` (`enrollmentId`,`subjectId`,`attempt_number`),
  KEY `fk_enrolledsubjects_enrollmentid` (`enrollmentId`),
  KEY `fk_enrolledsubjects_subjectid` (`subjectId`),
  KEY `fk_enrolledsubjects_blockid` (`blockId`),
  KEY `fk_enrolledsubjects_scheduleid` (`scheduleId`),
  KEY `idx_enrolledsubjects_enrollment_status` (`enrollmentId`,`status`),
  KEY `enrolledsubjects_original_enrolled_subject_id_foreign` (`original_enrolled_subject_id`),
  KEY `idx_enrolledsubjects_block_status` (`blockId`,`status`),
  CONSTRAINT `enrolledsubjects_blockid_foreign` FOREIGN KEY (`blockId`) REFERENCES `blocks` (`blockId`) ON UPDATE CASCADE,
  CONSTRAINT `enrolledsubjects_enrollmentid_foreign` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`) ON UPDATE CASCADE,
  CONSTRAINT `enrolledsubjects_original_enrolled_subject_id_foreign` FOREIGN KEY (`original_enrolled_subject_id`) REFERENCES `enrolledsubjects` (`enrolledSubjectId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `enrolledsubjects_scheduleid_foreign` FOREIGN KEY (`scheduleId`) REFERENCES `schedules` (`scheduleId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_enrolledsubjects_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`)
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrolledsubjects`
--

LOCK TABLES `enrolledsubjects` WRITE;
/*!40000 ALTER TABLE `enrolledsubjects` DISABLE KEYS */;
INSERT INTO `enrolledsubjects` VALUES (157,53,1,53,53,NULL,'confirmed',1,NULL),(158,53,2,53,53,NULL,'confirmed',1,NULL),(159,53,3,53,53,NULL,'confirmed',1,NULL);
/*!40000 ALTER TABLE `enrolledsubjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `enrollmentId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `courseId` int NOT NULL,
  `majorId` int DEFAULT NULL,
  `termId` int NOT NULL,
  `yearLevel` int NOT NULL DEFAULT '1',
  `admissionId` int DEFAULT NULL,
  `studentType` enum('firstYear','continuing','transferee','shifter') COLLATE utf8mb4_unicode_ci NOT NULL,
  `enrollmentType` enum('new','old') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'old',
  `academicStanding` enum('regular','irregular') COLLATE utf8mb4_unicode_ci NOT NULL,
  `enrollmentStatus` enum('pending','evaluated','assessed','paid','enrolled','dropped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `evaluatedBy` int NOT NULL,
  `registrarProcessedBy` int DEFAULT NULL,
  `enrolledDate` date DEFAULT NULL,
  `formIssuedDate` date DEFAULT NULL,
  `formSignedDate` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`enrollmentId`),
  KEY `fk_enrollments_studentid` (`studentId`),
  KEY `fk_enrollments_courseid` (`courseId`),
  KEY `fk_enrollments_majorid` (`majorId`),
  KEY `fk_enrollments_termid` (`termId`),
  KEY `fk_enrollments_admissionid` (`admissionId`),
  KEY `fk_enrollments_evaluatedby` (`evaluatedBy`),
  KEY `fk_enrollments_registrarprocessedby` (`registrarProcessedBy`),
  KEY `idx_enrollments_term_status` (`termId`,`enrollmentStatus`),
  KEY `idx_enrollments_status_id` (`enrollmentStatus`,`enrollmentId`),
  CONSTRAINT `enrollments_admissionid_foreign` FOREIGN KEY (`admissionId`) REFERENCES `admissions` (`admissionId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `enrollments_courseid_foreign` FOREIGN KEY (`courseId`) REFERENCES `courses` (`courseId`) ON UPDATE CASCADE,
  CONSTRAINT `enrollments_evaluatedby_foreign` FOREIGN KEY (`evaluatedBy`) REFERENCES `staffusers` (`userId`) ON UPDATE CASCADE,
  CONSTRAINT `enrollments_majorid_foreign` FOREIGN KEY (`majorId`) REFERENCES `majors` (`majorId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `enrollments_registrarprocessedby_foreign` FOREIGN KEY (`registrarProcessedBy`) REFERENCES `staffusers` (`userId`) ON UPDATE CASCADE,
  CONSTRAINT `enrollments_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE,
  CONSTRAINT `enrollments_termid_foreign` FOREIGN KEY (`termId`) REFERENCES `academicterms` (`termId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (53,53,3,NULL,18,1,27,'firstYear','new','regular','enrolled',5,1,'2026-09-13',NULL,'2026-09-13','2026-09-13 15:17:56','2026-09-13 15:17:57'),(55,56,5,NULL,11,2,NULL,'continuing','old','regular','enrolled',1,NULL,'2026-09-13',NULL,NULL,'2026-09-13 16:15:20','2026-09-13 16:15:20');
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollmentstatushistory`
--

DROP TABLE IF EXISTS `enrollmentstatushistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollmentstatushistory` (
  `historyId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int NOT NULL,
  `fromStatus` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `toStatus` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changedBy` int DEFAULT NULL,
  `remarks` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`historyId`),
  KEY `fk_enrollmentstatushistory_enrollment` (`enrollmentId`),
  KEY `fk_enrollmentstatushistory_changedby` (`changedBy`),
  CONSTRAINT `enrollmentstatushistory_changedby_foreign` FOREIGN KEY (`changedBy`) REFERENCES `staffusers` (`userId`),
  CONSTRAINT `enrollmentstatushistory_enrollmentid_foreign` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`)
) ENGINE=InnoDB AUTO_INCREMENT=309 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollmentstatushistory`
--

LOCK TABLES `enrollmentstatushistory` WRITE;
/*!40000 ALTER TABLE `enrollmentstatushistory` DISABLE KEYS */;
INSERT INTO `enrollmentstatushistory` VALUES (209,53,'pending','evaluated',5,'Subject load proposed by evaluator','2026-09-13 04:17:57'),(210,53,'evaluated','assessed',4,'Assessment finalized','2026-09-13 04:17:57'),(211,53,'assessed','paid',3,'Full payment received','2026-09-13 04:17:57'),(212,53,'paid','enrolled',1,'Registrar approved enrollment','2026-09-13 04:17:57');
/*!40000 ALTER TABLE `enrollmentstatushistory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollmentworkflow`
--

DROP TABLE IF EXISTS `enrollmentworkflow`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollmentworkflow` (
  `workflowId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int NOT NULL,
  `currentStep` int NOT NULL,
  `workflowStatus` enum('inProgress','completed','lost') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`workflowId`),
  KEY `fk_enrollmentworkflow_enrollmentid` (`enrollmentId`),
  CONSTRAINT `enrollmentworkflow_enrollmentid_foreign` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollmentworkflow`
--

LOCK TABLES `enrollmentworkflow` WRITE;
/*!40000 ALTER TABLE `enrollmentworkflow` DISABLE KEYS */;
INSERT INTO `enrollmentworkflow` VALUES (53,53,7,'completed');
/*!40000 ALTER TABLE `enrollmentworkflow` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `examresults`
--

DROP TABLE IF EXISTS `examresults`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `examresults` (
  `examId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `courseId` int NOT NULL,
  `termId` int NOT NULL,
  `examStage` enum('entrance','retention') COLLATE utf8mb4_unicode_ci NOT NULL,
  `examType` enum('general','courseSpecific') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `examResult` enum('pass','fail') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `examDate` date NOT NULL,
  PRIMARY KEY (`examId`),
  KEY `idx_examresults_student` (`studentId`,`courseId`),
  KEY `fk_examresults_studentid` (`studentId`),
  KEY `fk_examresults_courseid` (`courseId`),
  KEY `fk_examresults_termid` (`termId`),
  CONSTRAINT `examresults_courseid_foreign` FOREIGN KEY (`courseId`) REFERENCES `courses` (`courseId`) ON UPDATE CASCADE,
  CONSTRAINT `examresults_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE,
  CONSTRAINT `examresults_termid_foreign` FOREIGN KEY (`termId`) REFERENCES `academicterms` (`termId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `examresults`
--

LOCK TABLES `examresults` WRITE;
/*!40000 ALTER TABLE `examresults` DISABLE KEYS */;
INSERT INTO `examresults` VALUES (27,53,3,18,'entrance','general','pass','2026-09-13'),(28,53,3,18,'entrance','courseSpecific','pass','2026-09-13'),(29,54,5,18,'retention','courseSpecific','pass','2026-09-13');
/*!40000 ALTER TABLE `examresults` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feetypes`
--

DROP TABLE IF EXISTS `feetypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feetypes` (
  `feeTypeId` int NOT NULL AUTO_INCREMENT,
  `feeName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `defaultAmount` decimal(10,2) NOT NULL,
  `unitBasis` enum('perUnit','flat') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`feeTypeId`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feetypes`
--

LOCK TABLES `feetypes` WRITE;
/*!40000 ALTER TABLE `feetypes` DISABLE KEYS */;
INSERT INTO `feetypes` VALUES (1,'Tuition Fee (per unit)',1250.00,'perUnit'),(2,'Miscellaneous Fee',1500.00,'flat'),(3,'Laboratory Fee',500.00,'perUnit'),(4,'Library Fee',250.00,'flat');
/*!40000 ALTER TABLE `feetypes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gradescale`
--

DROP TABLE IF EXISTS `gradescale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gradescale` (
  `gradeScaleId` int NOT NULL AUTO_INCREMENT,
  `minGrade` decimal(3,2) NOT NULL,
  `maxGrade` decimal(3,2) NOT NULL,
  `isPassing` tinyint(1) NOT NULL,
  `description` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`gradeScaleId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gradescale`
--

LOCK TABLES `gradescale` WRITE;
/*!40000 ALTER TABLE `gradescale` DISABLE KEYS */;
/*!40000 ALTER TABLE `gradescale` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guardians`
--

DROP TABLE IF EXISTS `guardians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guardians` (
  `guardianId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `relationship` enum('mother','father','guardian','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contactNumber` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isEmergencyContact` tinyint(1) NOT NULL,
  `isAuthorizedToActOnBehalf` tinyint(1) NOT NULL,
  PRIMARY KEY (`guardianId`),
  KEY `fk_guardians_studentid` (`studentId`),
  CONSTRAINT `guardians_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guardians`
--

LOCK TABLES `guardians` WRITE;
/*!40000 ALTER TABLE `guardians` DISABLE KEYS */;
INSERT INTO `guardians` VALUES (79,53,'mother','Maria Dela Cruz','09171234568','demo.maria@example.com',1,1);
/*!40000 ALTER TABLE `guardians` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `idrequests`
--

DROP TABLE IF EXISTS `idrequests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idrequests` (
  `idRequestId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int NOT NULL,
  `requestReason` enum('newStudent','shifted','lost','replaced','renewed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `emergencyContactName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emergencyContactNumber` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bloodType` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cardPhotoPath` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `producedByVendor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requestDate` date NOT NULL,
  `status` enum('pending','cardProduced','validated','released','reissuePending','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reissueReason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_reissue` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`idRequestId`),
  KEY `fk_idrequests_enrollmentid` (`enrollmentId`),
  CONSTRAINT `idrequests_enrollmentid_foreign` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `idrequests`
--

LOCK TABLES `idrequests` WRITE;
/*!40000 ALTER TABLE `idrequests` DISABLE KEYS */;
INSERT INTO `idrequests` VALUES (53,53,'newStudent','Maria Dela Cruz','09171234568','O+',NULL,NULL,'2026-09-13','validated',NULL,0,'2026-09-13 15:17:57','2026-09-13 15:17:58');
/*!40000 ALTER TABLE `idrequests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
INSERT INTO `jobs` VALUES (1,'default','{\"uuid\":\"3b91f431-a8d9-4881-ac0e-edaf672985fd\",\"displayName\":\"App\\\\Listeners\\\\SendEnrollmentNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendEnrollmentNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:34:\\\"App\\\\Events\\\\EnrollmentStatusChanged\\\":5:{s:10:\\\"enrollment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Enrollments\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:1:{i:0;s:4:\\\"term\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:10:\\\"fromStatus\\\";s:7:\\\"pending\\\";s:8:\\\"toStatus\\\";s:9:\\\"evaluated\\\";s:9:\\\"changedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:5;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:7:\\\"remarks\\\";s:34:\\\"Subject load proposed by evaluator\\\";}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(2,'default','{\"uuid\":\"4c973c86-cc2f-4c49-b05d-d4dca5235574\",\"displayName\":\"App\\\\Listeners\\\\SendEnrollmentNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendEnrollmentNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:34:\\\"App\\\\Events\\\\EnrollmentStatusChanged\\\":5:{s:10:\\\"enrollment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Enrollments\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:1:{i:0;s:4:\\\"term\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:10:\\\"fromStatus\\\";s:7:\\\"pending\\\";s:8:\\\"toStatus\\\";s:9:\\\"evaluated\\\";s:9:\\\"changedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:5;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:7:\\\"remarks\\\";s:34:\\\"Subject load proposed by evaluator\\\";}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(3,'default','{\"uuid\":\"19300ce7-33db-466e-afa8-607e55401f9e\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:339;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:5;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(4,'default','{\"uuid\":\"ea188e08-478c-4a1d-9dd7-201e1dab93ce\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:339;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:5;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(5,'default','{\"uuid\":\"d9397a7a-cfa4-46d5-a6cf-9f1d132f31b2\",\"displayName\":\"App\\\\Listeners\\\\SendEnrollmentNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendEnrollmentNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:34:\\\"App\\\\Events\\\\EnrollmentStatusChanged\\\":5:{s:10:\\\"enrollment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Enrollments\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:10:\\\"fromStatus\\\";s:9:\\\"evaluated\\\";s:8:\\\"toStatus\\\";s:8:\\\"assessed\\\";s:9:\\\"changedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:4;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:7:\\\"remarks\\\";s:20:\\\"Assessment finalized\\\";}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(6,'default','{\"uuid\":\"9b2d1813-374d-4b2f-bed3-da5074acc672\",\"displayName\":\"App\\\\Listeners\\\\SendEnrollmentNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendEnrollmentNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:34:\\\"App\\\\Events\\\\EnrollmentStatusChanged\\\":5:{s:10:\\\"enrollment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Enrollments\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:10:\\\"fromStatus\\\";s:9:\\\"evaluated\\\";s:8:\\\"toStatus\\\";s:8:\\\"assessed\\\";s:9:\\\"changedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:4;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:7:\\\"remarks\\\";s:20:\\\"Assessment finalized\\\";}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(7,'default','{\"uuid\":\"9535f1e3-c78f-49b4-abf5-684097c4d175\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:340;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:4;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(8,'default','{\"uuid\":\"e5bb2cbf-9bfb-4e4f-8f08-5d28de1b8d3a\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:340;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:4;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(9,'default','{\"uuid\":\"cac62129-64f2-46f7-aafc-97db0d314c80\",\"displayName\":\"App\\\\Listeners\\\\SendEnrollmentNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendEnrollmentNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:34:\\\"App\\\\Events\\\\EnrollmentStatusChanged\\\":5:{s:10:\\\"enrollment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Enrollments\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:10:\\\"fromStatus\\\";s:8:\\\"assessed\\\";s:8:\\\"toStatus\\\";s:4:\\\"paid\\\";s:9:\\\"changedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:3;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:7:\\\"remarks\\\";s:21:\\\"Full payment received\\\";}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(10,'default','{\"uuid\":\"e4491802-7df5-4a03-a4d0-1a7a5035229b\",\"displayName\":\"App\\\\Listeners\\\\SendEnrollmentNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendEnrollmentNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:34:\\\"App\\\\Events\\\\EnrollmentStatusChanged\\\":5:{s:10:\\\"enrollment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Enrollments\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:10:\\\"fromStatus\\\";s:8:\\\"assessed\\\";s:8:\\\"toStatus\\\";s:4:\\\"paid\\\";s:9:\\\"changedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:3;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:7:\\\"remarks\\\";s:21:\\\"Full payment received\\\";}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(11,'default','{\"uuid\":\"a18dbb0e-6c61-41f1-bc93-8dc30b87e05d\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:341;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:3;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(12,'default','{\"uuid\":\"5abf26d1-c75d-4fab-b5df-2b5a0f4bd895\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:341;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:3;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(13,'default','{\"uuid\":\"bcc0a8e4-b963-4c79-96ad-4edc9181c4ae\",\"displayName\":\"App\\\\Listeners\\\\SendEnrollmentNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendEnrollmentNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:34:\\\"App\\\\Events\\\\EnrollmentStatusChanged\\\":5:{s:10:\\\"enrollment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Enrollments\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:1:{i:0;s:18:\\\"studentassessments\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:10:\\\"fromStatus\\\";s:4:\\\"paid\\\";s:8:\\\"toStatus\\\";s:8:\\\"enrolled\\\";s:9:\\\"changedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:3:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:7:\\\"remarks\\\";s:29:\\\"Registrar approved enrollment\\\";}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(14,'default','{\"uuid\":\"bbe131cc-e72c-4a41-90c9-d7a77409f417\",\"displayName\":\"App\\\\Listeners\\\\SendEnrollmentNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendEnrollmentNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:34:\\\"App\\\\Events\\\\EnrollmentStatusChanged\\\":5:{s:10:\\\"enrollment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Enrollments\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:1:{i:0;s:18:\\\"studentassessments\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:10:\\\"fromStatus\\\";s:4:\\\"paid\\\";s:8:\\\"toStatus\\\";s:8:\\\"enrolled\\\";s:9:\\\"changedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:3:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:7:\\\"remarks\\\";s:29:\\\"Registrar approved enrollment\\\";}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(15,'default','{\"uuid\":\"7926716e-afc8-4747-914b-d048411b28e8\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:342;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:3:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(16,'default','{\"uuid\":\"de9f9bd7-925c-4697-98e0-6d5471a50c2b\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:342;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:3:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(17,'default','{\"uuid\":\"4c960123-f3c9-43bb-9899-56b5184ee4e4\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:343;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:6;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(18,'default','{\"uuid\":\"ba3b323a-0c30-476a-a386-44bd02f0e73f\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:343;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:6;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(19,'default','{\"uuid\":\"85f7dea2-9157-4aae-a2be-56cb89467769\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:344;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:10;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(20,'default','{\"uuid\":\"6ab986a8-6fc2-4002-b171-fa54bfa5fc84\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:344;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:10;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273077,\"delay\":null}',0,NULL,1789273077,1789273077),(21,'default','{\"uuid\":\"2945c3a3-baa9-43f9-99d2-c21c1554d2d2\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:345;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:11;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273078,\"delay\":null}',0,NULL,1789273078,1789273078),(22,'default','{\"uuid\":\"77ec50aa-d08e-4d60-9826-23b870d23a8e\",\"displayName\":\"App\\\\Listeners\\\\SendWorkflowNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":28:{s:5:\\\"class\\\";s:38:\\\"App\\\\Listeners\\\\SendWorkflowNotification\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:29:\\\"App\\\\Events\\\\WorkflowStepSigned\\\":3:{s:8:\\\"workflow\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:29:\\\"App\\\\Models\\\\Enrollmentworkflow\\\";s:2:\\\"id\\\";i:53;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:4:\\\"step\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:24:\\\"App\\\\Models\\\\Workflowsteps\\\";s:2:\\\"id\\\";i:345;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:8:\\\"signedBy\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:21:\\\"App\\\\Models\\\\Staffusers\\\";s:2:\\\"id\\\";i:11;s:9:\\\"relations\\\";a:4:{i:0;s:6:\\\"office\\\";i:1;s:4:\\\"unit\\\";i:2;s:5:\\\"roles\\\";i:3;s:11:\\\"permissions\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";N;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:23:\\\"deleteWhenMissingModels\\\";b:0;s:14:\\\"shouldBeUnique\\\";b:0;s:29:\\\"shouldBeUniqueUntilProcessing\\\";b:0;s:8:\\\"uniqueId\\\";N;s:9:\\\"uniqueFor\\\";N;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789273078,\"delay\":null}',0,NULL,1789273078,1789273078);
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `majors`
--

DROP TABLE IF EXISTS `majors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `majors` (
  `majorId` int NOT NULL AUTO_INCREMENT,
  `courseId` int NOT NULL,
  `majorName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`majorId`),
  KEY `fk_majors_courseid` (`courseId`),
  CONSTRAINT `majors_courseid_foreign` FOREIGN KEY (`courseId`) REFERENCES `courses` (`courseId`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `majors`
--

LOCK TABLES `majors` WRITE;
/*!40000 ALTER TABLE `majors` DISABLE KEYS */;
/*!40000 ALTER TABLE `majors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_08_02_081526_create_permission_tables',1),(5,'2026_08_02_1000000001_create_academicyears_table',1),(6,'2026_08_02_1000000002_create_academicterms_table',1),(7,'2026_08_02_1000000003_create_academicunits_table',1),(8,'2026_08_02_1000000004_create_religions_table',1),(9,'2026_08_02_1000000005_create_students_table',1),(10,'2026_08_02_1000000006_create_addresses_table',1),(11,'2026_08_02_1000000007_create_admissionrequirements_table',1),(12,'2026_08_02_1000000008_create_courses_table',1),(13,'2026_08_02_1000000009_create_offices_table',1),(14,'2026_08_02_1000000010_create_staffusers_table',1),(15,'2026_08_02_1000000011_create_admissions_table',1),(16,'2026_08_02_1000000012_create_auditlogs_table',1),(17,'2026_08_02_1000000013_create_blocks_table',1),(18,'2026_08_02_1000000014_create_majors_table',1),(19,'2026_08_02_1000000015_create_enrollments_table',1),(20,'2026_08_02_1000000016_create_studentassessments_table',1),(21,'2026_08_02_1000000017_create_feetypes_table',1),(22,'2026_08_02_1000000018_create_charges_table',1),(23,'2026_08_02_1000000019_create_clearancerequirements_table',1),(24,'2026_08_02_1000000020_create_clearanceperiods_table',1),(25,'2026_08_02_1000000021_create_studentclearances_table',1),(26,'2026_08_02_1000000022_create_clearanceapprovals_table',1),(27,'2026_08_02_1000000023_create_clinicrecords_table',1),(28,'2026_08_02_1000000024_create_subjects_table',1),(29,'2026_08_02_1000000025_create_educationalinstitutions_table',1),(30,'2026_08_02_1000000026_create_transferacademicrecords_table',1),(31,'2026_08_02_1000000027_create_creditedsubjects_table',1),(32,'2026_08_02_1000000028_create_curriculums_table',1),(33,'2026_08_02_1000000029_create_curriculumsubjects_table',1),(34,'2026_08_02_1000000030_create_documentprintlog_table',1),(35,'2026_08_02_1000000031_create_studentrequirementsubmissions_table',1),(36,'2026_08_02_1000000032_create_documents_table',1),(37,'2026_08_02_1000000033_create_rooms_table',1),(38,'2026_08_02_1000000034_create_schedules_table',1),(39,'2026_08_02_1000000035_create_enrolledsubjects_table',1),(40,'2026_08_02_1000000036_create_enrollmentstatushistory_table',1),(41,'2026_08_02_1000000037_create_enrollmentworkflow_table',1),(42,'2026_08_02_1000000038_create_examresults_table',1),(43,'2026_08_02_1000000039_create_gradescale_table',1),(44,'2026_08_02_1000000040_create_guardians_table',1),(45,'2026_08_02_1000000041_create_idrequests_table',1),(46,'2026_08_02_1000000042_create_notifications_table',1),(47,'2026_08_02_1000000043_create_payments_table',1),(48,'2026_08_02_1000000044_create_schedulemeetings_table',1),(49,'2026_08_02_1000000045_create_scholarshiptypes_table',1),(50,'2026_08_02_1000000046_create_settings_table',1),(51,'2026_08_02_1000000047_create_studenteducationalbackgrounds_table',1),(52,'2026_08_02_1000000048_create_studentids_table',1),(53,'2026_08_02_1000000049_create_studentscholarships_table',1),(54,'2026_08_02_1000000050_create_workflowsteps_table',1),(55,'2026_08_02_1000000051_add_role_meta_columns_to_permission_tables',1),(56,'2026_08_04_1000000052_add_composite_indexes_for_heavy_screens',1),(57,'2026_08_05_1000000053_make_enrolledsubjects_blockid_nullable',1),(58,'2026_08_05_1000000054_add_auto_increment_to_pk_columns',1),(59,'2026_08_05_1000000055_make_optional_photo_and_institution_columns_nullable',1),(60,'2026_08_09_1000000004_extend_enrolledsubjects_table',1),(61,'2026_08_09_1000000005_extend_curriculumsubjects_table',1),(62,'2026_08_09_1000000015_extend_idrequests_table',1),(63,'2026_08_09_1000000016_widen_idrequests_status_enum',1),(64,'2026_08_09_1000000017_widen_clinicrecords_status_enum',1),(65,'2026_08_09_1000000018_add_performance_indexes',1),(66,'2026_08_10_044744_make_documentprintlog_enrollment_id_nullable',1),(67,'2026_08_15_192848_make_payments_enrollment_id_nullable',1),(68,'2026_08_15_200932_harden_optional_columns_nullable',1),(69,'2026_08_15_202053_add_missing_document_types_to_documentprintlog',1),(70,'2026_08_24_1000000070_realign_seait_org_structure',1),(71,'2026_08_29_1000000080_add_record_timestamps_to_transactional_tables',1),(72,'2026_09_06_000001_add_application_mode_to_admissions_table',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permissionId` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permissionId`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permissionid_foreign` FOREIGN KEY (`permissionId`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` json NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1599 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,'system','App\\Models\\Staffusers',1,'{\"message\": \"Welcome to the SEAIT Enrollment Management System. You can manage enrollment workflows from the sidebar.\", \"signedBy\": \"System\"}',NULL,'2026-08-30 17:56:30','2026-08-30 17:56:30'),(2,'workflow_step_signed','App\\Models\\Staffusers',1,'{\"message\": \"A student has completed the Department Evaluation step and is now waiting for Assessment.\", \"signedBy\": \"Workflow Service\", \"stepLabel\": \"Department Evaluation\", \"enrollmentId\": null}',NULL,'2026-08-30 17:56:30','2026-08-30 17:56:30');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `offices`
--

DROP TABLE IF EXISTS `offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offices` (
  `officeId` int NOT NULL AUTO_INCREMENT,
  `officeName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`officeId`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `offices`
--

LOCK TABLES `offices` WRITE;
/*!40000 ALTER TABLE `offices` DISABLE KEYS */;
INSERT INTO `offices` VALUES (1,'Registrar'),(2,'Accounting'),(3,'Scholarship'),(4,'Guidance'),(5,'Blocking'),(6,'Admission'),(7,'Academic Department'),(8,'Clearance'),(11,'Clinic'),(22,'ID Office');
/*!40000 ALTER TABLE `offices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `paymentId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int DEFAULT NULL,
  `orNumber` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paymentDate` datetime NOT NULL,
  `paymentMode` enum('cash','online') COLLATE utf8mb4_unicode_ci NOT NULL,
  `processedBy` int DEFAULT NULL,
  `paymentStatus` enum('paid','partial','pending') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`paymentId`),
  UNIQUE KEY `uq_payments_ornumber` (`orNumber`),
  KEY `fk_payments_enrollmentid` (`enrollmentId`),
  KEY `fk_payments_processedby` (`processedBy`),
  CONSTRAINT `fk_payments_enrollmentid` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `payments_processedby_foreign` FOREIGN KEY (`processedBy`) REFERENCES `staffusers` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (53,53,'DEMO-OR-0001',17500.00,'2026-09-13 00:00:00','cash',3,'paid','2026-09-13 15:17:57','2026-09-13 15:17:57');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'admission.view','web','admission','2026-08-30 17:56:27','2026-08-30 17:56:27'),(2,'admission.create','web','admission','2026-08-30 17:56:27','2026-08-30 17:56:27'),(3,'admission.update','web','admission','2026-08-30 17:56:27','2026-08-30 17:56:27'),(4,'admission.approve','web','admission','2026-08-30 17:56:27','2026-08-30 17:56:27'),(5,'admission.reject','web','admission','2026-08-30 17:56:27','2026-08-30 17:56:27'),(6,'admission.delete','web','admission','2026-08-30 17:56:27','2026-08-30 17:56:27'),(7,'admission.requirements.submit','web','admission','2026-08-30 17:56:27','2026-08-30 17:56:27'),(8,'admission.requirements.verify','web','admission','2026-08-30 17:56:27','2026-08-30 17:56:27'),(9,'block.view','web','blocking','2026-08-30 17:56:27','2026-08-30 17:56:27'),(10,'block.manage','web','blocking','2026-08-30 17:56:27','2026-08-30 17:56:27'),(11,'block.assign','web','blocking','2026-08-30 17:56:27','2026-08-30 17:56:27'),(12,'block.schedules.manage','web','blocking','2026-08-30 17:56:27','2026-08-30 17:56:27'),(13,'block.capacity.check','web','blocking','2026-08-30 17:56:27','2026-08-30 17:56:27'),(14,'print.blockSchedule','web','blocking','2026-08-30 17:56:27','2026-08-30 17:56:27'),(15,'assessment.view','web','assessment','2026-08-30 17:56:27','2026-08-30 17:56:27'),(16,'assessment.compute','web','assessment','2026-08-30 17:56:27','2026-08-30 17:56:27'),(17,'assessment.scholarships.apply','web','assessment','2026-08-30 17:56:27','2026-08-30 17:56:27'),(18,'assessment.charges.adjust','web','assessment','2026-08-30 17:56:27','2026-08-30 17:56:27'),(19,'assessment.finalize','web','assessment','2026-08-30 17:56:27','2026-08-30 17:56:27'),(20,'clearance.view','web','clearance','2026-08-30 17:56:27','2026-08-30 17:56:27'),(21,'clearance.periods.manage','web','clearance','2026-08-30 17:56:27','2026-08-30 17:56:27'),(22,'clearance.slip.generate','web','clearance','2026-08-30 17:56:27','2026-08-30 17:56:27'),(23,'clearance.receipt.record','web','clearance','2026-08-30 17:56:27','2026-08-30 17:56:27'),(24,'clearance.approve','web','clearance','2026-08-30 17:56:27','2026-08-30 17:56:27'),(25,'clearance.slip.replace','web','clearance','2026-08-30 17:56:27','2026-08-30 17:56:27'),(26,'clinic.view','web','clinic','2026-08-30 17:56:27','2026-08-30 17:56:27'),(27,'clinic.record','web','clinic','2026-08-30 17:56:27','2026-08-30 17:56:27'),(28,'clinic.update','web','clinic','2026-08-30 17:56:27','2026-08-30 17:56:27'),(29,'clinic.sign','web','clinic','2026-08-30 17:56:27','2026-08-30 17:56:27'),(30,'clinic.reopen','web','clinic','2026-08-30 17:56:27','2026-08-30 17:56:27'),(31,'evaluation.view','web','evaluation','2026-08-30 17:56:27','2026-08-30 17:56:27'),(32,'evaluation.create','web','evaluation','2026-08-30 17:56:27','2026-08-30 17:56:27'),(33,'evaluation.profile.capture','web','evaluation','2026-08-30 17:56:27','2026-08-30 17:56:27'),(34,'evaluation.profile.capture.any','web','evaluation','2026-08-30 17:56:28','2026-08-30 17:56:28'),(35,'evaluation.subjects.propose','web','evaluation','2026-08-30 17:56:28','2026-08-30 17:56:28'),(36,'evaluation.subjects.propose.any','web','evaluation','2026-08-30 17:56:28','2026-08-30 17:56:28'),(37,'evaluation.credits.process','web','evaluation','2026-08-30 17:56:28','2026-08-30 17:56:28'),(38,'evaluation.sign','web','evaluation','2026-08-30 17:56:28','2026-08-30 17:56:28'),(39,'evaluation.sign.dean','web','evaluation','2026-08-30 17:56:28','2026-08-30 17:56:28'),(40,'enrollment.subjects.confirm','web','evaluation','2026-08-30 17:56:28','2026-08-30 17:56:28'),(41,'exam.view','web','exam','2026-08-30 17:56:28','2026-08-30 17:56:28'),(42,'exam.record.general','web','exam','2026-08-30 17:56:28','2026-08-30 17:56:28'),(43,'exam.record.courseSpecific','web','exam','2026-08-30 17:56:28','2026-08-30 17:56:28'),(44,'exam.record.retention','web','exam','2026-08-30 17:56:28','2026-08-30 17:56:28'),(45,'exam.verify.general','web','exam','2026-08-30 17:56:28','2026-08-30 17:56:28'),(46,'id.view','web','id','2026-08-30 17:56:28','2026-08-30 17:56:28'),(47,'id.request.create','web','id','2026-08-30 17:56:28','2026-08-30 17:56:28'),(48,'id.card.produce','web','id','2026-08-30 17:56:28','2026-08-30 17:56:28'),(49,'id.validate','web','id','2026-08-30 17:56:28','2026-08-30 17:56:28'),(50,'id.release','web','id','2026-08-30 17:56:28','2026-08-30 17:56:28'),(51,'id.sign','web','id','2026-08-30 17:56:28','2026-08-30 17:56:28'),(52,'id.reissue','web','id','2026-08-30 17:56:28','2026-08-30 17:56:28'),(53,'id.cancel','web','id','2026-08-30 17:56:28','2026-08-30 17:56:28'),(54,'payment.view','web','payment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(55,'payment.record','web','payment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(56,'payment.void','web','payment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(57,'payment.report.daily','web','payment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(58,'refdata.view','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(59,'refdata.courses.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(60,'refdata.majors.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(61,'refdata.curriculums.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(62,'refdata.curriculumSubjects.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(63,'refdata.subjects.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(64,'refdata.terms.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(65,'refdata.feeTypes.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(66,'refdata.scholarshipTypes.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(67,'refdata.offices.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(68,'refdata.rooms.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(69,'refdata.blocks.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(70,'refdata.admissionRequirements.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(71,'refdata.clearanceRequirements.manage','web','refdata','2026-08-30 17:56:28','2026-08-30 17:56:28'),(72,'enrollment.approve','web','enrollment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(73,'print.certificate','web','enrollment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(74,'print.classCard','web','enrollment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(75,'print.subjectLoad','web','enrollment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(76,'enrollment.studentdata.record','web','enrollment','2026-08-30 17:56:28','2026-08-30 17:56:28'),(77,'user.view','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(78,'user.create','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(79,'user.update','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(80,'user.update.any','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(81,'user.delete','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(82,'user.delete.any','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(83,'user.roles.assign','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(84,'user.roles.manage','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(85,'user.permissions.manage','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(86,'user.status.toggle','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(87,'audit.view','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(88,'settings.manage','web','user','2026-08-30 17:56:28','2026-08-30 17:56:28'),(89,'dashboard.view','web','dashboard','2026-08-30 17:56:28','2026-08-30 17:56:28'),(90,'students.view','web','students','2026-08-30 17:56:28','2026-08-30 17:56:28');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `religions`
--

DROP TABLE IF EXISTS `religions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `religions` (
  `religionId` int NOT NULL AUTO_INCREMENT,
  `religionName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`religionId`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `religions`
--

LOCK TABLES `religions` WRITE;
/*!40000 ALTER TABLE `religions` DISABLE KEYS */;
INSERT INTO `religions` VALUES (1,'Roman Catholic');
/*!40000 ALTER TABLE `religions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `permissionId` bigint unsigned NOT NULL,
  `roleId` bigint unsigned NOT NULL,
  PRIMARY KEY (`permissionId`,`roleId`),
  KEY `role_permissions_roleid_foreign` (`roleId`),
  CONSTRAINT `role_permissions_permissionid_foreign` FOREIGN KEY (`permissionId`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_roleid_foreign` FOREIGN KEY (`roleId`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,1),(27,1),(28,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(35,1),(36,1),(37,1),(38,1),(39,1),(40,1),(41,1),(42,1),(43,1),(44,1),(45,1),(46,1),(47,1),(48,1),(49,1),(50,1),(51,1),(52,1),(53,1),(54,1),(55,1),(56,1),(57,1),(58,1),(59,1),(60,1),(61,1),(62,1),(63,1),(64,1),(65,1),(66,1),(67,1),(68,1),(69,1),(70,1),(71,1),(72,1),(73,1),(74,1),(75,1),(76,1),(77,1),(78,1),(79,1),(80,1),(81,1),(82,1),(83,1),(84,1),(85,1),(86,1),(87,1),(88,1),(89,1),(90,1),(1,2),(2,2),(3,2),(4,2),(5,2),(6,2),(7,2),(8,2),(9,2),(10,2),(11,2),(12,2),(13,2),(14,2),(15,2),(16,2),(17,2),(18,2),(19,2),(20,2),(21,2),(22,2),(23,2),(24,2),(25,2),(26,2),(27,2),(28,2),(29,2),(30,2),(31,2),(32,2),(33,2),(34,2),(35,2),(36,2),(37,2),(38,2),(39,2),(40,2),(41,2),(42,2),(43,2),(44,2),(45,2),(46,2),(47,2),(48,2),(49,2),(50,2),(51,2),(52,2),(53,2),(54,2),(55,2),(56,2),(57,2),(58,2),(59,2),(60,2),(61,2),(62,2),(63,2),(64,2),(65,2),(66,2),(67,2),(68,2),(69,2),(70,2),(71,2),(72,2),(73,2),(74,2),(75,2),(76,2),(77,2),(78,2),(79,2),(80,2),(81,2),(82,2),(83,2),(84,2),(85,2),(86,2),(87,2),(88,2),(89,2),(90,2),(1,3),(2,3),(3,3),(4,3),(5,3),(7,3),(8,3),(77,3),(89,3),(90,3),(41,4),(42,4),(43,4),(44,4),(45,4),(77,4),(89,4),(90,4),(1,5),(31,5),(32,5),(33,5),(34,5),(35,5),(36,5),(37,5),(38,5),(40,5),(41,5),(77,5),(89,5),(90,5),(1,6),(2,6),(3,6),(4,6),(5,6),(7,6),(8,6),(31,6),(32,6),(33,6),(34,6),(35,6),(36,6),(37,6),(38,6),(39,6),(40,6),(41,6),(58,6),(77,6),(89,6),(90,6),(1,7),(31,7),(32,7),(35,7),(37,7),(38,7),(40,7),(41,7),(89,7),(90,7),(15,8),(16,8),(17,8),(18,8),(19,8),(77,8),(89,8),(90,8),(15,9),(54,9),(55,9),(56,9),(57,9),(77,9),(89,9),(90,9),(20,10),(22,10),(23,10),(77,10),(89,10),(90,10),(15,11),(20,11),(31,11),(54,11),(72,11),(73,11),(74,11),(75,11),(76,11),(77,11),(89,11),(90,11),(9,12),(10,12),(11,12),(12,12),(13,12),(14,12),(77,12),(89,12),(90,12),(26,13),(27,13),(28,13),(29,13),(30,13),(77,13),(89,13),(90,13),(46,14),(47,14),(48,14),(49,14),(50,14),(51,14),(52,14),(53,14),(77,14),(89,14),(90,14),(1,15),(2,15),(3,15),(4,15),(5,15),(7,15),(8,15),(9,15),(10,15),(11,15),(12,15),(15,15),(16,15),(19,15),(20,15),(21,15),(22,15),(23,15),(24,15),(26,15),(27,15),(28,15),(29,15),(30,15),(31,15),(32,15),(33,15),(35,15),(37,15),(38,15),(41,15),(42,15),(43,15),(44,15),(45,15),(46,15),(47,15),(48,15),(49,15),(50,15),(51,15),(52,15),(53,15),(54,15),(55,15),(57,15),(58,15),(72,15),(73,15),(74,15),(75,15),(76,15),(77,15),(87,15),(89,15),(90,15),(1,16),(9,16),(15,16),(20,16),(26,16),(31,16),(41,16),(46,16),(54,16),(58,16),(77,16),(87,16),(89,16),(9,17),(31,17),(32,17),(33,17),(35,17),(74,17),(75,17),(77,17),(89,17);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'SysAdmin','web','System Administrator with full access to all modules','2026-08-30 17:56:28','2026-08-30 17:56:28'),(2,'Admin','web','Administrator with full access to all modules','2026-08-30 17:56:28','2026-08-30 17:56:28'),(3,'AdmissionOfficer','web','Admission Officer with intake, requirement verification, and qualification permissions','2026-08-30 17:56:28','2026-08-30 17:56:28'),(4,'GuidanceStaff','web','Guidance counselor and entrance/retention exam scoring staff','2026-08-30 17:56:28','2026-08-30 17:56:28'),(5,'DeptEvaluator','web','Academic department evaluator for profile capture, curriculum subject proposals, and transfer credits','2026-08-30 17:56:28','2026-08-30 17:56:28'),(6,'Dean','web','College Dean with evaluation sign-off, curriculum review, and enrollment confirmation','2026-08-30 17:56:29','2026-08-30 17:56:29'),(7,'ProgramHead','web','Program Head with evaluation, subject proposal, and confirmation permissions','2026-08-30 17:56:29','2026-08-30 17:56:29'),(8,'ScholarshipOfficer','web','Scholarship & Assessment officer for grant verification and fee computation','2026-08-30 17:56:29','2026-08-30 17:56:29'),(9,'AccountingStaff','web','Cashier and accounting staff for payment collection, OR recording, and daily collection reports','2026-08-30 17:56:29','2026-08-30 17:56:29'),(10,'RegistrarDesk','web','Registrar desk staff for clearance receipt recording and student verification','2026-08-30 17:56:29','2026-08-30 17:56:29'),(11,'RegistrarApprover','web','Registrar officer for final enrollment approval, subject confirmation, certificate and class card printing','2026-08-30 17:56:29','2026-08-30 17:56:29'),(12,'BlockingCoordinator','web','Blocking coordinator for block section assignment, schedule management, and capacity verification','2026-08-30 17:56:29','2026-08-30 17:56:29'),(13,'ClinicStaff','web','School clinic health assessment and PhilHealth registration staff','2026-08-30 17:56:29','2026-08-30 17:56:29'),(14,'IdOfficer','web','ID Office staff for ID requests, photo validation, QR generation, and card release','2026-08-30 17:56:30','2026-08-30 17:56:30'),(15,'OfficeHead','web','Office Head with all view permissions and full module action permissions','2026-08-30 17:56:30','2026-08-30 17:56:30'),(16,'Staff','web','Staff with view-only access across all modules','2026-08-30 17:56:30','2026-08-30 17:56:30'),(17,'Instructor','web','College Faculty Instructor with evaluation, subject proposal, and schedule viewing permissions','2026-08-30 17:56:30','2026-08-30 17:56:30');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `roomId` int NOT NULL AUTO_INCREMENT,
  `roomName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int NOT NULL,
  `building` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`roomId`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (1,'Room 101',40,'Main Building');
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedulemeetings`
--

DROP TABLE IF EXISTS `schedulemeetings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedulemeetings` (
  `meetingId` int NOT NULL AUTO_INCREMENT,
  `scheduleId` int NOT NULL,
  `dayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') COLLATE utf8mb4_unicode_ci NOT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  PRIMARY KEY (`meetingId`),
  KEY `fk_schedulemeetings_scheduleid` (`scheduleId`),
  CONSTRAINT `schedulemeetings_scheduleid_foreign` FOREIGN KEY (`scheduleId`) REFERENCES `schedules` (`scheduleId`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedulemeetings`
--

LOCK TABLES `schedulemeetings` WRITE;
/*!40000 ALTER TABLE `schedulemeetings` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedulemeetings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedules` (
  `scheduleId` int NOT NULL AUTO_INCREMENT,
  `blockId` int NOT NULL,
  `subjectId` int NOT NULL,
  `instructorId` int NOT NULL,
  `roomId` int NOT NULL,
  PRIMARY KEY (`scheduleId`),
  KEY `fk_schedules_subjectid` (`subjectId`),
  KEY `fk_schedules_instructorid` (`instructorId`),
  KEY `fk_schedules_roomid` (`roomId`),
  KEY `idx_schedules_lookup` (`blockId`,`subjectId`),
  KEY `fk_schedules_blockid` (`blockId`),
  CONSTRAINT `fk_schedules_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`),
  CONSTRAINT `schedules_blockid_foreign` FOREIGN KEY (`blockId`) REFERENCES `blocks` (`blockId`) ON UPDATE CASCADE,
  CONSTRAINT `schedules_instructorid_foreign` FOREIGN KEY (`instructorId`) REFERENCES `staffusers` (`userId`) ON UPDATE CASCADE,
  CONSTRAINT `schedules_roomid_foreign` FOREIGN KEY (`roomId`) REFERENCES `rooms` (`roomId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
INSERT INTO `schedules` VALUES (53,53,1,1,1);
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholarshiptypes`
--

DROP TABLE IF EXISTS `scholarshiptypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scholarshiptypes` (
  `scholarshipTypeId` int NOT NULL AUTO_INCREMENT,
  `scholarshipName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `coverageType` enum('full','partial') COLLATE utf8mb4_unicode_ci NOT NULL,
  `coveragePercent` decimal(10,2) NOT NULL,
  PRIMARY KEY (`scholarshipTypeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholarshiptypes`
--

LOCK TABLES `scholarshiptypes` WRITE;
/*!40000 ALTER TABLE `scholarshiptypes` DISABLE KEYS */;
/*!40000 ALTER TABLE `scholarshiptypes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `settingKey` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `settingValue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`settingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_roles`
--

DROP TABLE IF EXISTS `staff_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_roles` (
  `roleId` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`roleId`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `staff_roles_roleid_foreign` FOREIGN KEY (`roleId`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_roles`
--

LOCK TABLES `staff_roles` WRITE;
/*!40000 ALTER TABLE `staff_roles` DISABLE KEYS */;
INSERT INTO `staff_roles` VALUES (1,'App\\Models\\Staffusers',1),(2,'App\\Models\\Staffusers',1),(11,'App\\Models\\Staffusers',2),(15,'App\\Models\\Staffusers',2),(9,'App\\Models\\Staffusers',3),(15,'App\\Models\\Staffusers',3),(8,'App\\Models\\Staffusers',4),(15,'App\\Models\\Staffusers',4),(4,'App\\Models\\Staffusers',5),(15,'App\\Models\\Staffusers',5),(12,'App\\Models\\Staffusers',6),(15,'App\\Models\\Staffusers',6),(3,'App\\Models\\Staffusers',7),(15,'App\\Models\\Staffusers',7),(15,'App\\Models\\Staffusers',8),(15,'App\\Models\\Staffusers',9),(13,'App\\Models\\Staffusers',10),(15,'App\\Models\\Staffusers',10),(14,'App\\Models\\Staffusers',11),(15,'App\\Models\\Staffusers',11);
/*!40000 ALTER TABLE `staff_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staffusers`
--

DROP TABLE IF EXISTS `staffusers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staffusers` (
  `userId` int NOT NULL AUTO_INCREMENT,
  `officeId` int DEFAULT NULL,
  `unitId` int DEFAULT NULL,
  `employeeNo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middleName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `passwordHash` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('staff','officeHead','dean','programHead','admin','instructor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contactNo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`userId`),
  UNIQUE KEY `uq_staff_employeeno` (`employeeNo`),
  UNIQUE KEY `uq_staff_username` (`username`),
  KEY `fk_staffusers_officeid` (`officeId`),
  KEY `fk_staffusers_unitid` (`unitId`),
  CONSTRAINT `staffusers_officeid_foreign` FOREIGN KEY (`officeId`) REFERENCES `offices` (`officeId`) ON UPDATE CASCADE,
  CONSTRAINT `staffusers_unitid_foreign` FOREIGN KEY (`unitId`) REFERENCES `academicunits` (`unitId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staffusers`
--

LOCK TABLES `staffusers` WRITE;
/*!40000 ALTER TABLE `staffusers` DISABLE KEYS */;
INSERT INTO `staffusers` VALUES (1,1,NULL,'EMP-00008','System','','Administrator','staff8','$2y$12$G5Z1IBnkclM.b2ZAniG9HeXn/JIu6p4GeMHLQ5.19HFZlLzdHuWC.',NULL,'admin','staff8@seait.edu.ph','','active'),(2,1,NULL,'EMP-00101','Registrar Head','','Staff','office1_head','$2y$12$2KGVszPTO7TZVVdLdMA1lu/tRPbJuLtvgIsVRwqaQ9nVbYLI8JBnS',NULL,'officeHead','office1_head@seait.edu.ph','','active'),(3,2,NULL,'EMP-00102','Accounting Head','','Staff','office2_head','$2y$12$j0u/ojb1Au7jASk43IQyzOtMaV9clLmlSzApgRNYt8k8E3FSmYBHK',NULL,'officeHead','office2_head@seait.edu.ph','','active'),(4,3,NULL,'EMP-00103','Scholarship Head','','Staff','office3_head','$2y$12$4V5CuKBmgRm7ZBpwy4TxUe/ZtoaROZw1CiQGf85Q6Vu/eQug4Xwem',NULL,'officeHead','office3_head@seait.edu.ph','','active'),(5,4,NULL,'EMP-00104','Guidance Head','','Staff','office4_head','$2y$12$aQkiBZxkjn/I58ERyeWqzuEwP5LWB9NbvTeBrXdg1LumMQccwPSBS',NULL,'officeHead','office4_head@seait.edu.ph','','active'),(6,5,NULL,'EMP-00105','Blocking Head','','Staff','office5_head','$2y$12$4qOkrPUtzSQpTJnqJ1fsCe4FCulfvT20F1cu0VzRHNGSVGn15AJeS',NULL,'officeHead','office5_head@seait.edu.ph','','active'),(7,6,NULL,'EMP-00106','Admission Head','','Staff','office6_head','$2y$12$hpq5oq8J4pVGnhM9P./SUOpoOTBWUArk2o5vmSka62TJt5R.jZzVC',NULL,'officeHead','office6_head@seait.edu.ph','','active'),(8,7,NULL,'EMP-00107','Academic Head','','Staff','office7_head','$2y$12$ZbBuDah5Ov72cnVIvvYSi.2z/lu0/V8usNYobrGd88mN0NumUURE2',NULL,'officeHead','office7_head@seait.edu.ph','','active'),(9,8,NULL,'EMP-00108','Clearance Head','','Staff','office8_head','$2y$12$pNaEaES/LpCWCDzgqoSz1uQsbbKya3AWmy972aJFr.SK/3vncHmHC',NULL,'officeHead','office8_head@seait.edu.ph','','active'),(10,11,NULL,'EMP-00109','Clinic Head','','Staff','office11_head','$2y$12$rJ2F3fX9ZLAq25okdD8ZguBAxL97gKI5MkiBmrcNB8Kc7KhCyeYdK',NULL,'officeHead','office11_head@seait.edu.ph','','active'),(11,22,NULL,'EMP-00110','ID Head','','Staff','office22_head','$2y$12$KrAwNGX/KNzJf3KhUUZROu2/BAkAlSpKiS2gQK74TZcgnyzOQaXuq',NULL,'officeHead','office22_head@seait.edu.ph','','active');
/*!40000 ALTER TABLE `staffusers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `studentassessments`
--

DROP TABLE IF EXISTS `studentassessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `studentassessments` (
  `assessmentId` int NOT NULL AUTO_INCREMENT,
  `enrollmentId` int NOT NULL,
  `totalAssessedAmount` decimal(10,2) NOT NULL,
  `totalScholarshipCoverage` decimal(10,2) NOT NULL,
  `totalWaived` decimal(10,2) NOT NULL,
  `remainingBalance` decimal(10,2) NOT NULL,
  `assessmentDate` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`assessmentId`),
  KEY `fk_studentassessments_enrollmentid` (`enrollmentId`),
  CONSTRAINT `studentassessments_enrollmentid_foreign` FOREIGN KEY (`enrollmentId`) REFERENCES `enrollments` (`enrollmentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `studentassessments`
--

LOCK TABLES `studentassessments` WRITE;
/*!40000 ALTER TABLE `studentassessments` DISABLE KEYS */;
INSERT INTO `studentassessments` VALUES (53,53,17500.00,0.00,0.00,0.00,'2026-09-13','2026-09-13 15:17:57','2026-09-13 15:17:57');
/*!40000 ALTER TABLE `studentassessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `studentclearances`
--

DROP TABLE IF EXISTS `studentclearances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `studentclearances` (
  `studentClearanceId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `clearancePeriodId` int NOT NULL,
  `overallStatus` enum('pending','approved','rejected','waived','incomplete') COLLATE utf8mb4_unicode_ci NOT NULL,
  `extendedDeadline` date DEFAULT NULL,
  `receivedBy` int DEFAULT NULL,
  `receivedDate` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`studentClearanceId`),
  UNIQUE KEY `uq_student_clearanceperiod` (`studentId`,`clearancePeriodId`),
  KEY `fk_studentclearances_clearanceperiodid` (`clearancePeriodId`),
  KEY `fk_studentclearances_receivedby` (`receivedBy`),
  CONSTRAINT `studentclearances_clearanceperiodid_foreign` FOREIGN KEY (`clearancePeriodId`) REFERENCES `clearanceperiods` (`clearancePeriodId`) ON UPDATE CASCADE,
  CONSTRAINT `studentclearances_receivedby_foreign` FOREIGN KEY (`receivedBy`) REFERENCES `staffusers` (`userId`),
  CONSTRAINT `studentclearances_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `studentclearances`
--

LOCK TABLES `studentclearances` WRITE;
/*!40000 ALTER TABLE `studentclearances` DISABLE KEYS */;
INSERT INTO `studentclearances` VALUES (27,54,1,'approved',NULL,1,'2026-09-13 04:17:58','2026-09-13 15:17:58','2026-09-13 15:17:58');
/*!40000 ALTER TABLE `studentclearances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `studenteducationalbackgrounds`
--

DROP TABLE IF EXISTS `studenteducationalbackgrounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `studenteducationalbackgrounds` (
  `backgroundId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `institutionId` int NOT NULL,
  `levelCompleted` enum('elementary','juniorHigh','seniorHigh','vocational','college') COLLATE utf8mb4_unicode_ci NOT NULL,
  `strandTrack` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `yearCompleted` date NOT NULL,
  `honorsCertifications` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supportingDocumentPath` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`backgroundId`),
  KEY `fk_studenteducationalbackgrounds_studentid` (`studentId`),
  KEY `fk_studenteducationalbackgrounds_institutionid` (`institutionId`),
  CONSTRAINT `studenteducationalbackgrounds_institutionid_foreign` FOREIGN KEY (`institutionId`) REFERENCES `educationalinstitutions` (`institutionId`) ON UPDATE CASCADE,
  CONSTRAINT `studenteducationalbackgrounds_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `studenteducationalbackgrounds`
--

LOCK TABLES `studenteducationalbackgrounds` WRITE;
/*!40000 ALTER TABLE `studenteducationalbackgrounds` DISABLE KEYS */;
INSERT INTO `studenteducationalbackgrounds` VALUES (27,53,53,'seniorHigh','STEM','2024-03-31','','');
/*!40000 ALTER TABLE `studenteducationalbackgrounds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `studentids`
--

DROP TABLE IF EXISTS `studentids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `studentids` (
  `idId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `idRequestId` int NOT NULL,
  `qrCode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issueDate` date NOT NULL,
  `validationStatus` enum('pendingValidation','active','lost','replaced') COLLATE utf8mb4_unicode_ci NOT NULL,
  `securityPhotoPath` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validatedBy` int DEFAULT NULL,
  `validatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`idId`),
  UNIQUE KEY `uq_studentids_qrcode` (`qrCode`),
  KEY `fk_studentids_studentid` (`studentId`),
  KEY `fk_studentids_idrequestid` (`idRequestId`),
  KEY `fk_studentids_validatedby` (`validatedBy`),
  CONSTRAINT `studentids_idrequestid_foreign` FOREIGN KEY (`idRequestId`) REFERENCES `idrequests` (`idRequestId`) ON UPDATE CASCADE,
  CONSTRAINT `studentids_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE,
  CONSTRAINT `studentids_validatedby_foreign` FOREIGN KEY (`validatedBy`) REFERENCES `staffusers` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `studentids`
--

LOCK TABLES `studentids` WRITE;
/*!40000 ALTER TABLE `studentids` DISABLE KEYS */;
INSERT INTO `studentids` VALUES (53,53,53,'SEAIT-DEMO-53','2026-09-13','active',NULL,11,'2026-09-13 04:17:58');
/*!40000 ALTER TABLE `studentids` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `studentrequirementsubmissions`
--

DROP TABLE IF EXISTS `studentrequirementsubmissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `studentrequirementsubmissions` (
  `submissionId` int NOT NULL AUTO_INCREMENT,
  `admissionId` int NOT NULL,
  `requirementId` int NOT NULL,
  `submissionStatus` enum('submitted','verified','rejected','incomplete','pending') COLLATE utf8mb4_unicode_ci NOT NULL,
  `submittedDate` date NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`submissionId`),
  KEY `fk_studentrequirementsubmissions_admissionid` (`admissionId`),
  KEY `fk_studentrequirementsubmissions_requirementid` (`requirementId`),
  CONSTRAINT `studentrequirementsubmissions_admissionid_foreign` FOREIGN KEY (`admissionId`) REFERENCES `admissions` (`admissionId`) ON UPDATE CASCADE,
  CONSTRAINT `studentrequirementsubmissions_requirementid_foreign` FOREIGN KEY (`requirementId`) REFERENCES `admissionrequirements` (`requirementId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `studentrequirementsubmissions`
--

LOCK TABLES `studentrequirementsubmissions` WRITE;
/*!40000 ALTER TABLE `studentrequirementsubmissions` DISABLE KEYS */;
INSERT INTO `studentrequirementsubmissions` VALUES (66,27,1,'verified','2026-09-13',''),(67,27,2,'verified','2026-09-13',''),(68,27,4,'verified','2026-09-13','');
/*!40000 ALTER TABLE `studentrequirementsubmissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `studentId` int NOT NULL AUTO_INCREMENT,
  `schoolIdNumber` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middleName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suffix` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female') COLLATE utf8mb4_unicode_ci NOT NULL,
  `birthdate` date NOT NULL,
  `birthplace` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `citizenship` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `civilStatus` enum('single','married','widowed','separated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `religionId` int DEFAULT NULL,
  `contactNumber` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephoneNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `semestersCompleted` int DEFAULT NULL,
  `yearsInInstitution` int DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `passwordHash` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`studentId`),
  UNIQUE KEY `uq_students_schoolid` (`schoolIdNumber`),
  UNIQUE KEY `uq_students_username` (`username`),
  KEY `fk_students_religionid` (`religionId`),
  KEY `idx_students_names` (`lastName`,`firstName`),
  CONSTRAINT `students_religionid_foreign` FOREIGN KEY (`religionId`) REFERENCES `religions` (`religionId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (53,'DEMO-2026-001','Dela Cruz','Juan','P','N/A','male','2004-01-01','Test City','Filipino','single',1,'09171234567',NULL,0,0,'demo.juan@example.com','demo_juan','$2y$12$WgAezIk7lySetiJ4u.rw/uNpWSwcpC3rZHba6s.4/7Ln2IiLhJLDm','active','2026-09-13 15:17:56','2026-09-13 15:17:56'),(54,'DEMO-2026-002','Reyes','Maria','S','N/A','female','2003-05-15','Test City','Filipino','single',1,'09171234570',NULL,4,2,'demo.maria.reyes@example.com','demo_maria_r','$2y$12$wmJSm7ig3dVd1RYyh5mbl.4PQTDLmJdmOyviaUWAK0N1xkwzqk9Eq','active','2026-09-13 15:17:58','2026-09-13 15:17:58'),(55,'DEMO-2026-003','Santos','Pedro','R','N/A','male','2004-03-20','Test City','Filipino','single',1,'09171234580',NULL,0,0,'demo.pedro@example.com','demo_pedro','$2y$12$IJIdvY0VAJULVNiVf//HLedfj2LuwiQ1uJGBx5p0hhZHvmLyHdAAS','active','2026-09-13 16:03:28','2026-09-13 16:03:28'),(56,'DEMO-2026-004','Bautista','Liza','M','N/A','female','2003-11-02','Test City','Filipino','single',1,'09171234581',NULL,4,2,'demo.liza@example.com','demo_liza','$2y$12$urIJkoGR3ShASkr1hgZVPu0bvBWwOz1cZprzjuWwVy31LML4zDzgW','active','2026-09-13 16:03:29','2026-09-13 16:03:29');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `studentscholarships`
--

DROP TABLE IF EXISTS `studentscholarships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `studentscholarships` (
  `studentScholarshipId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `scholarshipTypeId` int NOT NULL,
  `termId` int NOT NULL,
  `status` enum('active','revoked','expired') COLLATE utf8mb4_unicode_ci NOT NULL,
  `approvedBy` int NOT NULL,
  `awardedBeforeEnrollment` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`studentScholarshipId`),
  UNIQUE KEY `uq_student_scholarship_term` (`studentId`,`scholarshipTypeId`,`termId`),
  KEY `fk_studentscholarships_scholarshiptypeid` (`scholarshipTypeId`),
  KEY `fk_studentscholarships_termid` (`termId`),
  KEY `fk_studentscholarships_approvedby` (`approvedBy`),
  KEY `idx_studentscholarships_lookup` (`studentId`,`termId`),
  CONSTRAINT `studentscholarships_approvedby_foreign` FOREIGN KEY (`approvedBy`) REFERENCES `staffusers` (`userId`) ON UPDATE CASCADE,
  CONSTRAINT `studentscholarships_scholarshiptypeid_foreign` FOREIGN KEY (`scholarshipTypeId`) REFERENCES `scholarshiptypes` (`scholarshipTypeId`) ON UPDATE CASCADE,
  CONSTRAINT `studentscholarships_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE,
  CONSTRAINT `studentscholarships_termid_foreign` FOREIGN KEY (`termId`) REFERENCES `academicterms` (`termId`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `studentscholarships`
--

LOCK TABLES `studentscholarships` WRITE;
/*!40000 ALTER TABLE `studentscholarships` DISABLE KEYS */;
/*!40000 ALTER TABLE `studentscholarships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `subjectId` int NOT NULL AUTO_INCREMENT,
  `subjectCode` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subjectName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lectureUnits` decimal(3,1) NOT NULL,
  `labUnits` decimal(3,1) NOT NULL,
  `subjectType` enum('lecture','lab','both') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`subjectId`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,'GEN101','General Education 1',3.0,0.0,'lecture'),(2,'ENG101','English 1',3.0,0.0,'lecture'),(3,'MATH101','Mathematics 1',3.0,0.0,'lecture'),(4,'CRIM101','Introduction to Criminology',3.0,0.0,'lecture'),(5,'IT101','Introduction to Computing',3.0,0.0,'lecture'),(6,'COM101','Communication Skills',3.0,0.0,'lecture');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transferacademicrecords`
--

DROP TABLE IF EXISTS `transferacademicrecords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transferacademicrecords` (
  `transferRecordId` int NOT NULL AUTO_INCREMENT,
  `studentId` int NOT NULL,
  `institutionId` int NOT NULL,
  `subjectNameAtOldSchool` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unitsAtOldSchool` decimal(3,1) NOT NULL,
  `gradeAtOldSchool` decimal(4,2) DEFAULT NULL,
  `passResult` enum('passed','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`transferRecordId`),
  KEY `fk_transferacademicrecords_studentid` (`studentId`),
  KEY `fk_transferacademicrecords_institutionid` (`institutionId`),
  CONSTRAINT `transferacademicrecords_institutionid_foreign` FOREIGN KEY (`institutionId`) REFERENCES `educationalinstitutions` (`institutionId`) ON UPDATE CASCADE,
  CONSTRAINT `transferacademicrecords_studentid_foreign` FOREIGN KEY (`studentId`) REFERENCES `students` (`studentId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transferacademicrecords`
--

LOCK TABLES `transferacademicrecords` WRITE;
/*!40000 ALTER TABLE `transferacademicrecords` DISABLE KEYS */;
/*!40000 ALTER TABLE `transferacademicrecords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workflowsteps`
--

DROP TABLE IF EXISTS `workflowsteps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflowsteps` (
  `workflowStepId` int NOT NULL AUTO_INCREMENT,
  `workflowId` int NOT NULL,
  `officeId` int NOT NULL,
  `stepOrder` int NOT NULL,
  `stepStatus` enum('pending','completed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `signedBy` int DEFAULT NULL,
  `signedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`workflowStepId`),
  KEY `fk_workflowsteps_workflowid` (`workflowId`),
  KEY `fk_workflowsteps_officeid` (`officeId`),
  KEY `fk_workflowsteps_signedby` (`signedBy`),
  KEY `idx_workflowsteps_workflow_order` (`workflowId`,`stepOrder`),
  CONSTRAINT `workflowsteps_officeid_foreign` FOREIGN KEY (`officeId`) REFERENCES `offices` (`officeId`) ON UPDATE CASCADE,
  CONSTRAINT `workflowsteps_signedby_foreign` FOREIGN KEY (`signedBy`) REFERENCES `staffusers` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `workflowsteps_workflowid_foreign` FOREIGN KEY (`workflowId`) REFERENCES `enrollmentworkflow` (`workflowId`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=502 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workflowsteps`
--

LOCK TABLES `workflowsteps` WRITE;
/*!40000 ALTER TABLE `workflowsteps` DISABLE KEYS */;
INSERT INTO `workflowsteps` VALUES (339,53,4,1,'completed',5,'2026-09-13 04:17:57'),(340,53,3,2,'completed',4,'2026-09-13 04:17:57'),(341,53,2,3,'completed',3,'2026-09-13 04:17:57'),(342,53,1,4,'completed',1,'2026-09-13 04:17:57'),(343,53,5,5,'completed',6,'2026-09-13 04:17:57'),(344,53,11,6,'completed',10,'2026-09-13 04:17:57'),(345,53,22,7,'completed',11,'2026-09-13 04:17:58');
/*!40000 ALTER TABLE `workflowsteps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'ems'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-12 19:37:10
