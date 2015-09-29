<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet exclude-result-prefixes="mods" version="2.0" xmlns="http://datacite.org/schema/kernel-3"
                        xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output media-type="text/xml" method="xml" indent="yes" encoding="UTF-8"/>
          
    <!-- if the DOI is supplied as a parameter then use that rather than the one from the MODS instance -->
    <xsl:param name="doi"/>
    <xsl:template match="/">
        <resource xsi:schemaLocation="http://datacite.org/schema/kernel-3 http://schema.datacite.org/meta/kernel-3/metadata.xsd">                  
            <identifier identifierType="DOI">
                <xsl:choose>
                    <xsl:when test="$doi">
                        <xsl:value-of select="$doi"/>
                    </xsl:when>
                    <xsl:otherwise>                        
                        <xsl:value-of select="//mods:mods/mods:identifier[@type='doi'][1]/text()"/>                        
                    </xsl:otherwise>
                </xsl:choose>
            </identifier> 
            <creators>
                <xsl:for-each select="//mods:mods/mods:name/mods:role/mods:roleTerm">
                    <xsl:choose>
                        <xsl:when test="contains(./text(), 'Creator')">
                            <creator>
                                <creatorName>
                                    <xsl:value-of select="../../mods:namePart[@type='family']/text()"/><xsl:text> </xsl:text>
                                    <xsl:value-of select="../../mods:namePart[@type='given']/text()"/><xsl:value-of select="../../mods:namePart[not(@type)]/text()"/>
                                </creatorName>
                            </creator>
                        </xsl:when>
                        <xsl:when test="contains(./text(), 'Author')">
                            <creator>
                                <creatorName>
                                    <xsl:value-of select="../../mods:namePart[@type='family']/text()"/><xsl:text> </xsl:text>
                                    <xsl:value-of select="../../mods:namePart[@type='given']/text()"/><xsl:value-of select="../../mods:namePart[not(@type)]/text()"/>
                                </creatorName>
                            </creator>
                        </xsl:when>
                    </xsl:choose>
                </xsl:for-each>
            </creators>
            <titles>
                <title>
                    <xsl:value-of select="//mods:mods/mods:titleInfo/mods:title/text()"/>
                </title>
                <xsl:if test="//mods:mods/mods:titleInfo/mods:subtitle">
                    <title titleType="Subtitle">
                        <xsl:value-of select="//mods:mods/mods:titleInfo/mods:subtitle/text()"/>
                    </title>
                </xsl:if>
            </titles>
            <xsl:if test="//mods:mods/mods:titleInfo/mods:partName">
                <version>
                    <xsl:value-of select="//mods:mods/mods:titleInfo/mods:partName/text()"/>
                </version>
            </xsl:if>
            <subjects>
                <xsl:for-each select="//mods:mods/mods:subject">
                    <xsl:for-each select="mods:topic">
                        <subject>
                            <xsl:value-of select="."/>
                        </subject>
                    </xsl:for-each>
                </xsl:for-each>
            </subjects>
            <publisher>
                <xsl:value-of select="//mods:mods/mods:originInfo/mods:publisher/text()"/>
            </publisher>
            <xsl:if test="//mods:mods/mods:originInfo/mods:dateIssued">
                <publicationYear>
                    <xsl:value-of select="substring(//mods:mods/mods:originInfo/mods:dateIssued/text(),1,4)"/>
                </publicationYear>
            </xsl:if>
            <xsl:if test="//mods:mods/mods:abstract">
            <descriptions>
                <description descriptionType="Abstract">
                    <xsl:value-of select="//mods:mods/mods:abstract/text()"/>
                </description>
            </descriptions>
            </xsl:if>
            <xsl:if test="//mods:mods/mods:language/mods:languageTerm">
            <language>
                <xsl:value-of select="//mods:mods/mods:language/mods:languageTerm/text()"/>
            </language>
            </xsl:if>
            <resourceType resourceTypeGeneral="Dataset">Dataset</resourceType>
            <formats>
                <xsl:for-each select="//mods:mods/mods:physicalDescription/mods:internetMediaType">
                    <format>
                        <xsl:value-of select="text()"/>
                    </format>
                </xsl:for-each>
            </formats>
        </resource>         
    </xsl:template>
</xsl:stylesheet>
