Problem Description
https://bitespeed.notion.site/Bitespeed-Backend-Task-Identity-Reconciliation-53392ab01f
e149fab989422300423199
Based on the description, I found out what are the scenarios need to handled
Scenario 1: New Record
a. The request contains both new phone no and email which are not existed in the db
b. The request contains existing phone no but email which is not existed in the db and vice
versa
c. Send only phone no which is existed in the db
d. Send only email which is existed in the db
e. Send phone no which is not existed in the db
f. Send email which is not existed in the db
Scenario 2 : Record which are existed in the db and in the same
primary group
a. Both phone no and email are primary
b. Phone no in primary and email in secondary and vice versa
Scenario 3: Both email and phone no are existed in different primary
group
a. Phone no associated with R1 primary group and Email in R2 primary group and vice
versa
Scenario 4: Both email and phone no are existed in secondary rows.
Case 1: Belong to the same primary group
a. Send phone no only
b. Send email only
c. Send phone no and email existed in the same db row
d. Send phone no and email existed in the different db row
Case 2: Belong to different primary group
a. Send phone no in R1 primary row group and email in R2 secondary row group and vice
versa
b. Send phone no in R1 secondary row group and R2 secondary row group.
