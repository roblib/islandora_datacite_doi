<?php

namespace Drupal\islandora_datacite_doi\Utility;

/**
 * DataCite's controlled vocabularies, shared between the data profile form
 * (where some of these are chosen directly as select options) and the XML
 * builder in DataciteDOITrait (where others are validated against values
 * pulled from Drupal field content).
 */
final class DataciteVocabularies {

  /**
   * Valid values for resourceTypeGeneral and relatedItemType.
   */
  const RESOURCE_TYPES = [
    "Audiovisual",
    "Award",
    "Book",
    "BookChapter",
    "Collection",
    "ComputationalNotebook",
    "ConferencePaper",
    "ConferenceProceeding",
    "DataPaper",
    "Dataset",
    "Dissertation",
    "Event",
    "Image",
    "Instrument",
    "InteractiveResource",
    "Journal",
    "JournalArticle",
    "Model",
    "OutputManagementPlan",
    "PeerReview",
    "PhysicalObject",
    "Preprint",
    "Poster",
    "Presentation",
    "Project",
    "Report",
    "Service",
    "Software",
    "Sound",
    "Standard",
    "StudyRegistration",
    "Text",
    "Workflow",
    "Other",
  ];

  /**
   * Valid values for contributorType.
   */
  const CONTRIBUTOR_TYPES = [
    "ContactPerson",
    "DataCollector",
    "DataCurator",
    "DataManager",
    "Distributor",
    "Editor",
    "HostingInstitution",
    "Producer",
    "ProjectLeader",
    "ProjectManager",
    "ProjectMember",
    "RegistrationAgency",
    "RegistrationAuthority",
    "RelatedPerson",
    "Researcher",
    "ResearchGroup",
    "RightsHolder",
    "Sponsor",
    "Supervisor",
    "Translator",
    "WorkPackageLeader",
    "Other",
  ];

  /**
   * Valid values for relationType, used on both relatedIdentifier and
   * relatedItem.
   */
  const RELATION_TYPES = [
    "IsCitedBy",
    "Cites",
    "IsSupplementTo",
    "IsSupplementedBy",
    "IsContinuedBy",
    "Continues",
    "Describes",
    "IsDescribedBy",
    "HasMetadata",
    "IsMetadataFor",
    "HasVersion",
    "IsVersionOf",
    "IsNewVersionOf",
    "IsPreviousVersionOf",
    "IsPartOf",
    "HasPart",
    "IsPublishedIn",
    "IsReferencedBy",
    "References",
    "IsDocumentedBy",
    "Documents",
    "IsCompiledBy",
    "Compiles",
    "IsVariantFormOf",
    "IsOriginalFormOf",
    "IsIdenticalTo",
    "IsReviewedBy",
    "Reviews",
    "IsDerivedFrom",
    "IsSourceOf",
    "IsRequiredBy",
    "Requires",
    "Obsoletes",
    "IsObsoletedBy",
    "Collects",
    "IsCollectedBy",
    "HasTranslation",
    "IsTranslationOf",
    "Other",
  ];

  /**
   * Valid values for titleType. The main/primary title has no titleType
   * attribute at all; these apply only to additional titles.
   */
  const TITLE_TYPES = [
    "AlternativeTitle",
    "Subtitle",
    "TranslatedTitle",
    "Other",
  ];

  /**
   * Valid values for funderIdentifierType. Required whenever a
   * funderIdentifier is present.
   */
  const FUNDER_IDENTIFIER_TYPES = [
    "ISNI",
    "GRID",
    "ROR",
    "Crossref Funder ID",
    "Other",
  ];

  /**
   * Valid values for descriptionType. Unlike titleType/nameType, this
   * attribute is required on every description.
   */
  const DESCRIPTION_TYPES = [
    "Abstract",
    "Methods",
    "SeriesInformation",
    "TableOfContents",
    "TechnicalInfo",
    "Other",
  ];

  /**
   * Valid values for nameType, the attribute on creatorName/contributorName.
   */
  const NAME_TYPES = [
    "Personal",
    "Organizational",
  ];

  /**
   * Valid values for dateType.
   */
  const DATE_TYPES = [
    "Accepted",
    "Available",
    "Copyrighted",
    "Collected",
    "Coverage",
    "Created",
    "Issued",
    "Submitted",
    "Updated",
    "Valid",
    "Withdrawn",
    "Other",
  ];

  /**
   * Valid values for numberType, the attribute on relatedItem's "number".
   */
  const NUMBER_TYPES = [
    "Article",
    "Chapter",
    "Report",
    "Other",
  ];

  /**
   * Valid values for relatedIdentifierType and relatedItemIdentifierType.
   */
  const IDENTIFIER_TYPES = [
    "ARK",
    "arXiv",
    "bibcode",
    "CSTR",
    "DOI",
    "EAN13",
    "EISSN",
    "Handle",
    "IGSN",
    "ISBN",
    "ISSN",
    "ISTC",
    "LISSN",
    "LSID",
    "PMID",
    "PURL",
    "RAiD",
    "RRID",
    "SWHID",
    "UPC",
    "URL",
    "URN",
    "w3id",
  ];

}
