# Database Schema — TaisarTownHub (planned Firestore)

Collections: projects, properties, sellers, buyers, dealers, transactions, propertyFiles, plotMarkers, media, inquiries, cmsConfig, users, commissionPayments.

## properties
Public fields: title, description, pricePKR, formattedPrice, location, sector, block, streetNumber, plotNumber, fileNumber, sizeSqYards, sizeUnit, frontFeet, depthFeet, roadWidthFeet, type, status, isFeatured, images, videoUrl, features, bedrooms, bathrooms, possessionReady, mdaApproved, cornerPlot, westOpen, parkFacing, mainBoulevard, createdDate, contactPerson, contactPhone, contactWhatsApp.
Private (subcollection or restricted fields): purchasePricePKR, sellingPricePKR, sellerId, buyerId, assignedDealerId, internalNotes.

## dealers
totalEarnedPKR, pendingCommissionPKR, totalCommissionPaidPKR.

## transactions
status enum: Draft, Negotiation, Agreement Signed, Payment Pending, Partially Paid, Fully Paid, Completed, Cancelled, Under Transfer.
commissionStatus: Pending, Paid, Unpaid, Partially Paid.
commissionPaidPKR.

## commissionPayments (immutable)
id, dealerId, transactionId, amount, paidAt, paidBy, method, notes.

## Soft delete
sellers/buyers/dealers: isArchived boolean instead of hard delete when referenced.
